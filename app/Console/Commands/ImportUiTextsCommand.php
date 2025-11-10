<?php

namespace App\Console\Commands;

use App\Helpers\TranslationHelper;
use App\Models\UiText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportUiTextsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ui-texts:import {path : The CSV file path to import}
                            {--dry-run : Show what would be imported without actually importing}
                            {--force : Update existing records even if they have values}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import UI texts from a CSV file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = $this->argument('path');
        
        // Resolve full path
        // If path is relative, try storage/app first, then current directory
        if (!str_starts_with($path, '/')) {
            $fullPath = storage_path('app/' . ltrim($path, '/'));
            if (!File::exists($fullPath)) {
                $fullPath = base_path($path);
            }
        } else {
            $fullPath = $path;
        }
        
        // Check if file exists
        if (!File::exists($fullPath)) {
            $this->error("File not found: {$fullPath}");
            $this->info("Please provide a valid CSV file path.");
            return Command::FAILURE;
        }
        
        // Check if file is readable
        if (!is_readable($fullPath)) {
            $this->error("File is not readable: {$fullPath}");
            return Command::FAILURE;
        }
        
        $this->info("Reading CSV file: {$fullPath}");
        
        // Read CSV file
        $rows = $this->readCsvFile($fullPath);
        
        if (empty($rows)) {
            $this->warn('No data rows found in CSV file (only header or empty file).');
            return Command::SUCCESS;
        }
        
        $this->info("Found " . count($rows) . " row(s) to process.");
        
        if ($this->option('dry-run')) {
            $this->displayDryRunResults($rows);
            return Command::SUCCESS;
        }
        
        // Import data
        $results = $this->importRows($rows);
        
        // Clear cache
        TranslationHelper::clearCache();
        
        // Display results
        $this->newLine();
        $this->info("✓ Import completed!");
        $this->info("  - Created: {$results['created']}");
        $this->info("  - Updated: {$results['updated']}");
        $this->info("  - Skipped: {$results['skipped']}");
        $this->info("  - Errors: {$results['errors']}");
        $this->info("✓ Translation cache cleared.");
        
        return Command::SUCCESS;
    }

    /**
     * Read CSV file and return rows as array
     */
    protected function readCsvFile(string $path): array
    {
        $rows = [];
        $file = fopen($path, 'r');
        
        if ($file === false) {
            $this->error("Failed to open file: {$path}");
            return [];
        }
        
        // Read header row
        $header = fgetcsv($file);
        
        if ($header === false) {
            fclose($file);
            return [];
        }
        
        // Validate header
        $expectedColumns = ['key', 'value_en', 'value_ro', 'value_hu', 'description'];
        $header = array_map('trim', $header);
        
        foreach ($expectedColumns as $col) {
            if (!in_array($col, $header)) {
                $this->error("Missing required column in CSV: {$col}");
                $this->info("Expected columns: " . implode(', ', $expectedColumns));
                fclose($file);
                return [];
            }
        }
        
        // Create column index map
        $columnMap = array_flip($header);
        
        // Read data rows
        $rowNumber = 1; // Start at 1 because header is row 0
        while (($data = fgetcsv($file)) !== false) {
            $rowNumber++;
            
            // Skip empty rows
            if (empty(array_filter($data))) {
                continue;
            }
            
            // Map data to columns
            $row = [
                'key' => trim($data[$columnMap['key']] ?? ''),
                'value_en' => trim($data[$columnMap['value_en']] ?? ''),
                'value_ro' => trim($data[$columnMap['value_ro']] ?? ''),
                'value_hu' => trim($data[$columnMap['value_hu']] ?? ''),
                'description' => trim($data[$columnMap['description']] ?? ''),
                '_row_number' => $rowNumber,
            ];
            
            // Skip rows with empty key
            if (empty($row['key'])) {
                $this->warn("Row {$rowNumber}: Skipped (empty key)");
                continue;
            }
            
            $rows[] = $row;
        }
        
        fclose($file);
        
        return $rows;
    }

    /**
     * Display dry-run results
     */
    protected function displayDryRunResults(array $rows): void
    {
        $this->warn("\n[DRY RUN] The following changes would be made:\n");
        
        $tableData = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;
        
        foreach ($rows as $row) {
            $existing = UiText::where('key', $row['key'])->first();
            
            if (!$existing) {
                $status = 'CREATE';
                $created++;
            } elseif ($this->wouldUpdate($existing, $row)) {
                $status = 'UPDATE';
                $updated++;
            } else {
                $status = 'SKIP';
                $skipped++;
            }
            
            $tableData[] = [
                'key' => $row['key'],
                'status' => $status,
                'value_en' => mb_substr($row['value_en'] ?? '', 0, 30) . (mb_strlen($row['value_en'] ?? '') > 30 ? '...' : ''),
            ];
        }
        
        $this->table(['Key', 'Status', 'Value (EN)'], $tableData);
        
        $this->newLine();
        $this->info("Summary:");
        $this->info("  - Would create: {$created}");
        $this->info("  - Would update: {$updated}");
        $this->info("  - Would skip: {$skipped}");
        $this->info("\nRun without --dry-run to perform the import.");
    }

    /**
     * Import rows into database
     */
    protected function importRows(array $rows): array
    {
        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
        
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();
        
        foreach ($rows as $row) {
            try {
                $existing = UiText::where('key', $row['key'])->first();
                
                if (!$existing) {
                    // Create new record
                    UiText::create([
                        'key' => $row['key'],
                        'value_en' => $row['value_en'] ?: null,
                        'value_ro' => $row['value_ro'] ?: null,
                        'value_hu' => $row['value_hu'] ?: null,
                        'description' => $row['description'] ?: null,
                    ]);
                    $results['created']++;
                } elseif ($this->wouldUpdate($existing, $row)) {
                    // Update existing record
                    $existing->update([
                        'value_en' => $row['value_en'] ?: null,
                        'value_ro' => $row['value_ro'] ?: null,
                        'value_hu' => $row['value_hu'] ?: null,
                        'description' => $row['description'] ?: null,
                    ]);
                    $results['updated']++;
                } else {
                    // Skip (no changes or --force not used)
                    $results['skipped']++;
                }
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Error processing row {$row['_row_number']} (key: {$row['key']}): " . $e->getMessage());
                $results['errors']++;
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        
        return $results;
    }

    /**
     * Determine if a record would be updated
     */
    protected function wouldUpdate(UiText $existing, array $row): bool
    {
        // If --force is used, always update
        if ($this->option('force')) {
            return true;
        }
        
        // Update if CSV has non-empty values that differ from existing
        $columns = ['value_en', 'value_ro', 'value_hu', 'description'];
        
        foreach ($columns as $column) {
            $csvValue = $row[$column] ?? '';
            $existingValue = $existing->$column ?? '';
            
            // If CSV has a value (even if it's empty string), update it
            if (isset($row[$column])) {
                if ($csvValue !== $existingValue) {
                    return true;
                }
            }
        }
        
        return false;
    }
}
