<?php

namespace App\Console\Commands;

use App\Helpers\TranslationHelper;
use App\Models\UiText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateUiTextsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ui-texts:generate 
                            {--dry-run : Show what would be created without actually creating records}
                            {--force : Overwrite existing translations with default values}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan codebase for ui_label() calls and generate missing UI text translation records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Scanning codebase for ui_label() calls...');
        
        // Find all PHP files in the application
        $files = $this->getPhpFiles();
        $this->info("Found {$files->count()} PHP files to scan.");
        
        // Extract translation keys from ui_label() calls
        // Returns associative array: key => default_value
        $foundKeysWithDefaults = $this->extractTranslationKeys($files);
        $foundKeys = array_keys($foundKeysWithDefaults); // Get just the keys
        $this->info("Found " . count($foundKeys) . " unique translation keys.");
        
        if (empty($foundKeys)) {
            $this->warn('No ui_label() calls found in the codebase.');
            return Command::SUCCESS;
        }
        
        // Get existing keys from database
        $existingKeys = UiText::pluck('key')->toArray();
        
        // Find missing keys
        $missingKeys = array_diff($foundKeys, $existingKeys);
        
        if (empty($missingKeys)) {
            $this->info('✓ All translation keys already exist in the database.');
            return Command::SUCCESS;
        }
        
        $this->info("Found " . count($missingKeys) . " missing translation keys.");
        
        if ($this->option('dry-run')) {
            $this->displayDryRunResults($missingKeys, $foundKeysWithDefaults);
            return Command::SUCCESS;
        }
        
        // Create missing keys
        $created = $this->createMissingKeys($missingKeys, $foundKeysWithDefaults);
        
        // Clear cache to ensure new translations are available immediately
        TranslationHelper::clearCache();
        
        $this->info("✓ Created {$created} new translation key(s).");
        $this->info("✓ Translation cache cleared.");
        $this->info("You can now edit translations in the admin panel: System Settings → UI Texts");
        
        return Command::SUCCESS;
    }

    /**
     * Get all PHP and Blade files in the application (excluding vendor and node_modules)
     */
    protected function getPhpFiles()
    {
        $paths = [
            app_path(),
            resource_path('views'),
            base_path('routes'),
        ];
        
        $files = collect();
        
        foreach ($paths as $path) {
            if (File::exists($path)) {
                $allFiles = File::allFiles($path);
                $filteredFiles = collect($allFiles)
                    ->filter(fn ($file) => in_array($file->getExtension(), ['php', 'blade.php']));
                
                $files = $files->merge($filteredFiles);
            }
        }
        
        return $files;
    }

    /**
     * Extract translation keys from ui_label() calls
     * 
     * Returns an array where keys are translation keys and values are default values (if provided)
     */
    protected function extractTranslationKeys($files): array
    {
        $keysWithDefaults = [];
        
        foreach ($files as $file) {
            try {
                $content = File::get($file->getPathname());
                $lines = explode("\n", $content);
                
                // Pattern to match ui_label() calls
                // Handles both single and double quotes
                // Matches: ui_label('key'), ui_label('key', 'ro'), ui_label('key', null, 'Default')
                $pattern = '/ui_label\s*\(\s*(["\'])((?:(?!\1).)+)\1\s*(?:,\s*(?:[^,)]+))?\s*(?:,\s*(["\'])((?:(?!\3).)+)\3)?/';
                
                foreach ($lines as $lineNumber => $line) {
                    // Remove content after // (inline comments) but keep the line if it has code before
                    $codeOnly = preg_replace('/\/\/.*$/', '', $line);
                    
                    // Skip lines that are entirely comments (start with // or *)
                    $trimmedLine = trim($codeOnly);
                    if (empty($trimmedLine) || str_starts_with($trimmedLine, '*') || str_starts_with($line, '//')) {
                        continue;
                    }
                    
                    // Skip lines that are inside /* ... */ block comments
                    // Simple check: if line contains /* before any code, it's likely a comment
                    if (preg_match('/\/\*.*?\*\//', $codeOnly)) {
                        // Check if ui_label is inside the comment block
                        if (preg_match('/\/\*.*?ui_label.*?\*\//', $line)) {
                            continue;
                        }
                    }
                    
                    // Now search in the code-only part (comments removed)
                    if (preg_match_all($pattern, $codeOnly, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $match) {
                            $key = $match[2]; // Translation key (second capture group)
                            
                            // Check if default value is provided (third parameter - fourth capture group)
                            $defaultValue = null;
                            if (isset($match[4]) && !empty($match[4])) {
                                $defaultValue = $match[4]; // Default value
                            }
                            
                            // Store key and default value
                            // If key already exists and has no default, but we found one, use it
                            if (!isset($keysWithDefaults[$key])) {
                                $keysWithDefaults[$key] = $defaultValue;
                            } elseif ($defaultValue && empty($keysWithDefaults[$key])) {
                                // If we find a default value for a key that didn't have one, use it
                                $keysWithDefaults[$key] = $defaultValue;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Skip files that can't be read
                continue;
            }
        }
        
        // Return associative array: key => default_value
        return $keysWithDefaults;
    }

    /**
     * Display dry-run results
     */
    protected function displayDryRunResults(array $missingKeys, array $keysWithDefaults)
    {
        $this->warn("\n[DRY RUN] The following translation keys would be created:\n");
        
        $tableData = [];
        foreach ($missingKeys as $key) {
            $defaultValue = $keysWithDefaults[$key] ?? '';
            $tableData[] = [
                'key' => $key,
                'default_value' => $defaultValue ?: '(empty)',
            ];
        }
        
        $this->table(['Translation Key', 'Default Value (English)'], $tableData);
        
        $this->info("\nRun without --dry-run to create these records.");
    }

    /**
     * Create missing translation keys in the database
     */
    protected function createMissingKeys(array $missingKeys, array $keysWithDefaults): int
    {
        $created = 0;
        $bar = $this->output->createProgressBar(count($missingKeys));
        $bar->start();
        
        foreach ($missingKeys as $key) {
            // Get default value if provided in ui_label() call
            $defaultValue = $keysWithDefaults[$key] ?? '';
            
            // If no default value, generate one from the key
            if (empty($defaultValue)) {
                $defaultValue = $this->generateDefaultValue($key);
            }
            
            // Create the UiText record
            UiText::create([
                'key' => $key,
                'value_en' => $defaultValue, // Use default value as English translation
                'value_ro' => null, // Leave empty for manual translation
                'value_hu' => null, // Leave empty for manual translation
                'description' => "Auto-generated from ui_label() call. Key: {$key}",
            ]);
            
            $created++;
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        
        return $created;
    }

    /**
     * Generate a default value from a translation key
     * 
     * Examples:
     * 'quote.status.draft' -> 'Draft'
     * 'user.name' -> 'Name'
     * 'actions.save' -> 'Save'
     */
    protected function generateDefaultValue(string $key): string
    {
        // Extract the last part of the key (after the last dot)
        $parts = explode('.', $key);
        $lastPart = end($parts);
        
        // Convert snake_case or kebab-case to Title Case
        $value = Str::title(str_replace(['_', '-'], ' ', $lastPart));
        
        return $value;
    }
}
