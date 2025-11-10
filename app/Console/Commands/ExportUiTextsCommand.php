<?php

namespace App\Console\Commands;

use App\Models\UiText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ExportUiTextsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ui-texts:export {path? : The output file path (default: storage/app/ui_texts_export.csv)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export all UI texts to a CSV file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Exporting UI texts to CSV...');
        
        // Determine output path
        $path = $this->argument('path');
        
        if (empty($path)) {
            // Default path
            $path = 'ui_texts_export.csv';
        }
        
        // Resolve full path
        // If path is relative, assume it's relative to storage/app
        if (!str_starts_with($path, '/')) {
            $fullPath = storage_path('app/' . ltrim($path, '/'));
        } else {
            $fullPath = $path;
        }
        
        // Get all UI texts from database
        $uiTexts = UiText::orderBy('key')->get();
        
        // Create directory if it doesn't exist
        $directory = dirname($fullPath);
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
        
        // Open file for writing
        $file = fopen($fullPath, 'w');
        
        if ($file === false) {
            $this->error("Failed to open file for writing: {$fullPath}");
            return Command::FAILURE;
        }
        
        // Write CSV header
        fputcsv($file, ['key', 'value_en', 'value_ro', 'value_hu', 'description']);
        
        // Write data rows
        $count = 0;
        
        if ($uiTexts->isEmpty()) {
            $this->warn('No UI texts found in the database.');
            $this->info('Creating empty CSV file with headers only.');
        } else {
            $bar = $this->output->createProgressBar($uiTexts->count());
            $bar->start();
            
            foreach ($uiTexts as $uiText) {
                fputcsv($file, [
                    $uiText->key,
                    $uiText->value_en ?? '',
                    $uiText->value_ro ?? '',
                    $uiText->value_hu ?? '',
                    $uiText->description ?? '',
                ]);
                
                $count++;
                $bar->advance();
            }
            
            $bar->finish();
            $this->newLine();
        }
        
        // Close file
        fclose($file);
        
        // Display file location information
        $this->newLine();
        if ($count > 0) {
            $this->info("✓ Exported {$count} UI text(s) to CSV file.");
        } else {
            $this->info("✓ Created empty CSV file with headers.");
        }
        
        // Show both container path and host path
        $this->newLine();
        $this->info("File location:");
        $this->line("  Container path: {$fullPath}");
        
        // Try to show the relative path from project root
        $relativePath = str_replace(base_path() . '/', '', $fullPath);
        if ($relativePath !== $fullPath) {
            $this->line("  Project path: {$relativePath}");
        }
        
        // Also show the actual file system path if different
        $realPath = realpath($fullPath);
        if ($realPath && $realPath !== $fullPath) {
            $this->line("  Real path: {$realPath}");
        }
        
        $this->newLine();
        $this->info("You can now edit this file in Excel, Google Sheets, or any CSV editor.");
        
        // For Docker/Sail, provide helpful hint
        if (config('app.env') !== 'production') {
            $this->line("Note: If using Laravel Sail, the file is accessible at:");
            $this->line("  - Inside container: {$fullPath}");
            $this->line("  - On your host: ./storage/app/ui_texts_export.csv (relative to project root)");
        }
        
        return Command::SUCCESS;
    }
}
