<?php

namespace App\Filament\Resources\QuoteResource\RelationManagers;

use App\Models\Product;
use App\Models\ProductServicePricing;
use App\Models\Service;
use App\Services\ServiceCalculationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('Product')
                    ->relationship(
                        'product',
                        'name_en',
                        function ($query) {
                            $locale = app()->getLocale() ?? config('locales.default', 'en');
                            $column = "name_{$locale}";
                            return $query->select('products.id', "products.{$column} as name_en");
                        }
                    )
                    ->getOptionLabelUsing(fn ($value): ?string => \App\Models\Product::find($value)?->name)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                        if ($state) {
                            $product = Product::find($state);
                            if ($product) {
                                $set('unit_price', $product->base_price);
                                // If custom_name is empty, optionally set it to product name (user can override)
                                if (!$get('custom_name')) {
                                    // Don't auto-fill - let user decide if they want custom name
                                }
                                // Trigger calculation after setting unit price
                                $this->calculateTotals($set, $get);
                            }
                        }
                    })
                    ->native(false),
                Forms\Components\TextInput::make('custom_name')
                    ->label('Custom Display Name')
                    ->maxLength(255)
                    ->helperText('Optional: Override the product name with a custom name for this quote item. Leave empty to use the product name.')
                    ->placeholder('e.g., "Custom Glass Panel - Office Window"')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->step(0.01)
                    ->default(1)
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                        $this->calculateTotals($set, $get);
                    }),
                Forms\Components\TextInput::make('width_mm')
                    ->label('Width (mm)')
                    ->numeric()
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                        $this->calculateTotals($set, $get);
                    }),
                Forms\Components\TextInput::make('height_mm')
                    ->label('Height (mm)')
                    ->numeric()
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                        $this->calculateTotals($set, $get);
                    }),
                Forms\Components\TextInput::make('unit_price')
                    ->label('Unit Price (€/m²)')
                    ->numeric()
                    ->prefix('€')
                    ->step(0.01)
                    ->required()
                    ->default(0)
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                        $this->calculateTotals($set, $get);
                    })
                    ->helperText('Price per square meter'),
                Forms\Components\TextInput::make('product_total')
                    ->label('Product Total')
                    ->numeric()
                    ->prefix('€')
                    ->default(0)
                    ->disabled()
                    ->dehydrated(),
                Forms\Components\TextInput::make('linear_meter')
                    ->label('Linear Meter (lm)')
                    ->numeric()
                    ->step(0.01)
                    ->default(0)
                    ->disabled()
                    ->dehydrated()
                    ->helperText('Auto-calculated: 2 × (width + height) / 1000 × quantity'),
                Forms\Components\Select::make('discount_type')
                    ->options([
                        'none' => 'No Discount',
                        'fixed' => 'Fixed Amount',
                        'percentage' => 'Percentage',
                    ])
                    ->default('none')
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                        $this->calculateTotals($set, $get);
                    })
                    ->native(false),
                Forms\Components\TextInput::make('discount_value')
                    ->label('Discount Value')
                    ->numeric()
                    ->step(0.01)
                    ->default(0)
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                        $this->calculateTotals($set, $get);
                    }),
                // Services Section - Repeater for managing services
                // This section only appears when a product is selected
                Forms\Components\Section::make('Services')
                    ->schema([
                        Forms\Components\Repeater::make('services')
                            ->live() // Make the repeater reactive so disableOptionWhen updates when items change
                            ->schema([
                                Forms\Components\Select::make('service_id')
                                    ->label('Service')
                                    ->options(function () {
                                        // Show all active services
                                        try {
                                            return Service::where('is_active', true)
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->toArray();
                                        } catch (\Exception $e) {
                                            return [];
                                        }
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required()
                                    ->disableOptionWhen(function ($value, Forms\Get $get, $livewire) {
                                        if (!$value) {
                                            return false;
                                        }
                                        
                                        // Get all services from the repeater (all items)
                                        $allServices = $get('../../services') ?? [];
                                        $currentServiceId = $get('service_id'); // Current item's selected service (if any)
                                        
                                        // Check if this service is already selected in the repeater
                                        $isAlreadySelected = false;
                                        foreach ($allServices as $serviceData) {
                                            if (isset($serviceData['service_id']) && $serviceData['service_id'] == $value) {
                                                $isAlreadySelected = true;
                                                break;
                                            }
                                        }
                                        
                                        // If service is already selected AND it's not the current item's service, disable it
                                        if ($isAlreadySelected && $currentServiceId != $value) {
                                            return true; // Disable - already selected in another item
                                        }
                                        
                                        // Also check existing services from database (when editing existing quote item)
                                        if (isset($livewire->mountedTableActionRecord) && $livewire->mountedTableActionRecord instanceof \App\Models\QuoteItem) {
                                            $existingServiceIds = $livewire->mountedTableActionRecord->services->pluck('id')->toArray();
                                            // If service exists in DB and current item doesn't have it, disable
                                            if (in_array($value, $existingServiceIds) && $currentServiceId != $value) {
                                                return true; // Disable - exists in database from another service item
                                            }
                                        }
                                        
                                        // Allow selection
                                        return false;
                                    })
                                    ->helperText(function (Forms\Get $get) {
                                        // Show helper text if trying to select a duplicate
                                        $allServices = $get('../../services') ?? [];
                                        $selectedId = $get('service_id');
                                        
                                        if ($selectedId) {
                                            $count = 0;
                                            foreach ($allServices as $serviceData) {
                                                if (isset($serviceData['service_id']) && $serviceData['service_id'] == $selectedId) {
                                                    $count++;
                                                }
                                            }
                                            
                                            if ($count > 1) {
                                                return 'This service is already added. Each service can only be added once.';
                                            }
                                        }
                                        
                                        return '';
                                    })
                                    ->rules([
                                        function (Forms\Get $get) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                if (!$value) {
                                                    return;
                                                }
                                                
                                                // Get all services from the repeater
                                                $allServices = $get('../../services') ?? [];
                                                $duplicateCount = 0;
                                                
                                                // Count how many times this service appears
                                                foreach ($allServices as $serviceData) {
                                                    if (isset($serviceData['service_id']) && $serviceData['service_id'] == $value) {
                                                        $duplicateCount++;
                                                    }
                                                }
                                                
                                                // If it appears more than once, it's a duplicate
                                                if ($duplicateCount > 1) {
                                                    $service = Service::find($value);
                                                    $serviceName = $service ? $service->name : 'this service';
                                                    $fail("The service \"{$serviceName}\" is already added to this quote item. Each service can only be added once.");
                                                    return;
                                                }
                                            };
                                        },
                                    ])
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get, $livewire) {
                                        if (!$state) {
                                            return;
                                        }
                                        
                                        try {
                                            // FIRST: Check for duplicates and prevent selection if duplicate
                                            // Get all services from the repeater - this includes the current item's new selection
                                            $allServices = $get('../../services') ?? [];
                                            
                                            // Count how many times this service ID appears in ALL repeater items
                                            // Note: When you just selected a service, it will be in the array, so we need to check properly
                                            $duplicateCount = 0;
                                            foreach ($allServices as $serviceData) {
                                                if (isset($serviceData['service_id']) && $serviceData['service_id'] == $state) {
                                                    $duplicateCount++;
                                                }
                                            }
                                            
                                            // If it appears more than once, it's a duplicate - clear the selection immediately
                                            if ($duplicateCount > 1) {
                                                $service = Service::find($state);
                                                $serviceName = $service ? $service->name : 'this service';
                                                
                                                // Clear the selection to prevent duplicate
                                                $set('service_id', null);
                                                $set('price_per_unit', 0);
                                                $set('quantity', 1);
                                                $set('total', 0);
                                                
                                                // Try to show a notification
                                                try {
                                                    \Filament\Notifications\Notification::make()
                                                        ->title('Duplicate Service')
                                                        ->body("The service \"{$serviceName}\" is already added. Each service can only be added once per quote item.")
                                                        ->danger()
                                                        ->send();
                                                } catch (\Exception $e) {
                                                    // Notification might fail in some contexts, that's OK
                                                }
                                                
                                                return; // Stop processing - don't set prices or calculate
                                            }
                                            
                                            $service = Service::find($state);
                                            if (!$service) {
                                                return;
                                            }
                                            
                                            // Get product_id - try multiple sources
                                            $productId = null;
                                            
                                            // Method 1: From owner record (editing existing item)
                                            if (isset($livewire->ownerRecord) && $livewire->ownerRecord) {
                                                $productId = $livewire->ownerRecord->product_id ?? null;
                                            }
                                            
                                            // Method 2: From mounted action record (creating new item)
                                            if (!$productId && isset($livewire->mountedTableActionRecord) && $livewire->mountedTableActionRecord) {
                                                $productId = $livewire->mountedTableActionRecord->product_id ?? null;
                                            }
                                            
                                            // Method 3: From form data using get() - try parent form fields
                                            if (!$productId) {
                                                // In a repeater, parent form fields are at ../ level
                                                $productId = $get('../../product_id') ?? $get('../product_id') ?? $get('product_id');
                                            }
                                            
                                            // Method 4: Try accessing livewire form data directly
                                            if (!$productId && isset($livewire->data['product_id'])) {
                                                $productId = $livewire->data['product_id'];
                                            }
                                            
                                            // Set unit_type based on service's pricing_mode
                                            $unitType = match($service->pricing_mode ?? null) {
                                                Service::PRICING_PER_SQM => 'sqm',
                                                Service::PRICING_PER_LM => 'lm',
                                                Service::PRICING_PER_PIECE => 'piece',
                                                default => 'sqm',
                                            };
                                            $set('unit_type', $unitType);
                                            
                                            // Try to get pricing from product_service_pricing table
                                            if ($productId) {
                                                $pricing = ProductServicePricing::where('product_id', $productId)
                                                    ->where('service_id', $state)
                                                    ->first();
                                                if ($pricing) {
                                                    $set('price_per_unit', $pricing->price_per_unit);
                                                    // Override unit_type with pricing table value if it exists
                                                    if ($pricing->unit_type) {
                                                        $set('unit_type', $pricing->unit_type);
                                                    }
                                                } else {
                                                    // No pricing found - set default to 0, user can enter manually
                                                    $set('price_per_unit', 0);
                                                }
                                            } else {
                                                // No product_id found - set default to 0
                                                $set('price_per_unit', 0);
                                            }
                                            
                                            // Set default quantity based on unit type and quote item data
                                            // In RelationManager, ownerRecord is the Quote, not the QuoteItem
                                            // So we always create a temporary QuoteItem from form data
                                            $tempItem = null;
                                            
                                            if ($productId) {
                                                $tempItem = new \App\Models\QuoteItem();
                                                $tempItem->product_id = $productId;
                                                
                                                // Get quantity from parent form (we're in a repeater, so go up two levels)
                                                $quantity = $get('../../quantity') ?? $get('../quantity') ?? $get('quantity') ?? 1;
                                                $tempItem->quantity = (float) $quantity;
                                                
                                                // Get dimensions from parent form
                                                $tempItem->width_mm = $get('../../width_mm') ?? $get('../width_mm') ?? $get('width_mm');
                                                $tempItem->height_mm = $get('../../height_mm') ?? $get('../height_mm') ?? $get('height_mm');
                                                
                                                // Calculate surface area and linear meter if dimensions exist
                                                if ($tempItem->width_mm && $tempItem->height_mm) {
                                                    $areaPerUnit = ((float) $tempItem->width_mm * (float) $tempItem->height_mm) / 1000000;
                                                    $tempItem->surface_area_m2 = $areaPerUnit * $tempItem->quantity;
                                                    $linearMeterPerUnit = (2 * ((float) $tempItem->width_mm + (float) $tempItem->height_mm)) / 1000;
                                                    $tempItem->linear_meter = $linearMeterPerUnit * $tempItem->quantity;
                                                }
                                                
                                                // Get service default quantity if we have the temp item and service
                                                if ($service && $tempItem) {
                                                    $serviceCalcService = app(ServiceCalculationService::class);
                                                    $defaultQty = $serviceCalcService->getDefaultQuantity($tempItem, $service);
                                                    if ($defaultQty !== null) {
                                                        $set('quantity', $defaultQty);
                                                    }
                                                }
                                            }
                                            
                                            // Recalculate service total
                                            $this->calculateServiceTotalForRepeater($set, $get, $livewire);
                                        } catch (\Exception $e) {
                                            // Silently fail - user can still manually enter price
                                            \Log::error('Error in service afterStateUpdated: ' . $e->getMessage());
                                        }
                                    })
                                    ->native(false),
                                Forms\Components\Toggle::make('enabled')
                                    ->label('Enabled')
                                    ->default(true)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get, $livewire) {
                                        $this->calculateServiceTotalForRepeater($set, $get, $livewire);
                                    }),
                                Forms\Components\TextInput::make('price_per_unit')
                                    ->label('Unit Price')
                                    ->numeric()
                                    ->prefix('€')
                                    ->step(0.01)
                                    ->default(0)
                                    ->required()
                                    ->reactive()
                                    ->helperText(function (Forms\Get $get) {
                                        $unitType = $get('unit_type') ?? 'sqm';
                                        return match($unitType) {
                                            'sqm' => 'Price per m²',
                                            'lm' => 'Price per linear meter',
                                            'piece' => 'Price per piece',
                                            default => 'Price per unit',
                                        };
                                    })
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get, $livewire) {
                                        $this->calculateServiceTotalForRepeater($set, $get, $livewire);
                                    }),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->step(0.01)
                                    ->default(1)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get, $livewire) {
                                        $this->calculateServiceTotalForRepeater($set, $get, $livewire);
                                    })
                                    ->visible(function (Forms\Get $get) {
                                        $unitType = $get('../../unit_type') ?? 'sqm';
                                        return in_array($unitType, ['piece', 'sqm']);
                                    })
                                    ->helperText(function (Forms\Get $get) {
                                        $unitType = $get('../../unit_type') ?? 'sqm';
                                        return match($unitType) {
                                            'piece' => 'Number of pieces',
                                            'sqm' => 'Override surface area (m²)',
                                            default => ''
                                        };
                                    }),
                                Forms\Components\TextInput::make('total')
                                    ->label('Total')
                                    ->numeric()
                                    ->prefix('€')
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0),
                                Forms\Components\Hidden::make('unit_type'),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->minItems(0)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => 
                                isset($state['service_id']) && $state['service_id']
                                    ? Service::find($state['service_id'])?->name ?? 'New Service'
                                    : 'New Service'
                            )
                            ->addActionLabel('Add Service')
                            ->deleteAction(
                                fn ($action) => $action->label('Remove Service')
                            )
                            ->visible(function (Forms\Get $get, $livewire) {
                                // Check product_id from form data (reactive) or from owner record
                                $productId = $get('product_id') ?? ($livewire->ownerRecord->product_id ?? null);
                                return (bool) $productId;
                            })
                            ->reorderable(false)
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                // This runs before creating - ensure no duplicates
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                // This runs before saving - ensure no duplicates
                                return $data;
                            }),
                    ])
                    ->visible(function (Forms\Get $get, $livewire) {
                        // Check product_id from form data (reactive) or from owner record
                        // This makes the section appear/disappear when product is selected/changed
                        $productId = $get('product_id') ?? $livewire->ownerRecord->product_id ?? $livewire->mountedTableActionRecord->product_id ?? null;
                        return (bool) $productId;
                    })
                    ->collapsible(),
                Forms\Components\TextInput::make('service_total')
                    ->label('Service Total')
                    ->numeric()
                    ->prefix('€')
                    ->default(0)
                    ->disabled()
                    ->dehydrated(),
                Forms\Components\TextInput::make('accessory_total')
                    ->label('Accessory Total')
                    ->numeric()
                    ->prefix('€')
                    ->default(0)
                    ->disabled()
                    ->dehydrated(),
                Forms\Components\TextInput::make('line_total')
                    ->label('Line Total')
                    ->numeric()
                    ->prefix('€')
                    ->default(0)
                    ->disabled()
                    ->dehydrated(),
                Forms\Components\Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->hidden(),
            ])
            ->columns(2);
    }

    /**
     * Mutate form data before creating/updating a record
     * This ensures surface_area_m2 and linear_meter are calculated and stored correctly
     * Note: We preserve 'services' data so it can be processed in the after() hook
     * Also validates that services are not duplicated
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Calculate surface area and linear meter if dimensions are provided
        $widthMm = $data['width_mm'] ?? null;
        $heightMm = $data['height_mm'] ?? null;
        $quantity = (float) ($data['quantity'] ?? 1);

        if ($widthMm && $heightMm && $widthMm > 0 && $heightMm > 0) {
            // Calculate area per unit: (width_mm × height_mm) / 1,000,000
            $areaPerUnit = ((float) $widthMm * (float) $heightMm) / 1000000;
            // Store total area (area per unit × quantity) with 2 decimals
            $data['surface_area_m2'] = round($areaPerUnit * $quantity, 2);
            
            // Calculate linear meter per unit: 2 × (width_mm + height_mm) / 1000
            $linearMeterPerUnit = (2 * ((float) $widthMm + (float) $heightMm)) / 1000;
            // Store total linear meter (perimeter per unit × quantity) with 2 decimals
            $data['linear_meter'] = round($linearMeterPerUnit * $quantity, 2);
        } else {
            $data['surface_area_m2'] = null;
            $data['linear_meter'] = null;
        }

        // Store services data temporarily - we'll process it in after() hook
        // Remove it from the main data so it doesn't try to save it as a column
        $servicesData = $data['services'] ?? [];
        
        // Remove duplicate services BEFORE storing
        if (!empty($servicesData)) {
            $seenServiceIds = [];
            $uniqueServicesData = [];
            
            foreach ($servicesData as $serviceData) {
                if (!isset($serviceData['service_id']) || !$serviceData['service_id']) {
                    continue;
                }
                
                $serviceId = $serviceData['service_id'];
                
                // Skip if we've already seen this service_id
                if (in_array($serviceId, $seenServiceIds)) {
                    \Log::warning("Duplicate service removed in mutateFormDataBeforeSave: service_id {$serviceId}");
                    continue;
                }
                
                $seenServiceIds[] = $serviceId;
                $uniqueServicesData[] = $serviceData;
            }
            
            $this->pendingServices = $uniqueServicesData;
        }
        
        unset($data['services']);

        return $data;
    }
    
    /**
     * Temporary storage for services data
     */
    protected $pendingServices = [];

    /**
     * Calculate product_total and line_total using area-based pricing
     * 
     * Formula:
     * 1. Calculate area per unit: (width_mm × height_mm) / 1,000,000
     * 2. Calculate product total: area_per_unit (m²) × unit_price (€/m²) × quantity
     * 
     * If dimensions are missing, fallback to: unit_price × quantity
     */
    protected function calculateTotals(Forms\Set $set, Forms\Get $get): void
    {
        $quantity = (float) ($get('quantity') ?? 1);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $widthMm = $get('width_mm');
        $heightMm = $get('height_mm');
        $discountType = $get('discount_type') ?? 'none';
        $discountValue = (float) ($get('discount_value') ?? 0);
        $serviceTotal = (float) ($get('service_total') ?? 0);
        $accessoryTotal = (float) ($get('accessory_total') ?? 0);

        // Step 1: Calculate surface area per unit (m²)
        // Formula: (width_mm × height_mm) / 1,000,000
        $areaPerUnit = null;
        if ($widthMm && $heightMm && $widthMm > 0 && $heightMm > 0) {
            $areaPerUnit = ((float) $widthMm * (float) $heightMm) / 1000000;
        }

        // Step 1b: Calculate linear meter per unit (lm)
        // Formula: 2 × (width_mm + height_mm) / 1000
        $linearMeterPerUnit = null;
        if ($widthMm && $heightMm && $widthMm > 0 && $heightMm > 0) {
            $linearMeterPerUnit = (2 * ((float) $widthMm + (float) $heightMm)) / 1000;
            // Store total linear meter (perimeter per unit × quantity)
            $set('linear_meter', round($linearMeterPerUnit * $quantity, 2));
        } else {
            $set('linear_meter', null);
        }

        // Step 2: Calculate product total using area-based pricing
        // Formula: area_per_unit (m²) × unit_price (€/m²) × quantity
        if ($areaPerUnit !== null && $areaPerUnit > 0) {
            // Area-based pricing: area (m²) × price (€/m²) × quantity
            $productTotal = $areaPerUnit * $unitPrice * $quantity;
        } else {
            // Fallback: no dimensions or zero area, use simple calculation
            // This treats it as if each unit has 1.0 m² area (backward compatibility)
            $productTotal = $unitPrice * $quantity;
        }
        
        $set('product_total', round($productTotal, 2));

        // Step 3: Calculate line_total (product_total + services + accessories - discount)
        // Note: service_total will be calculated from the services repeater
        $lineSubtotal = $productTotal + $serviceTotal + $accessoryTotal;
        
        // Step 4: Apply discount
        $discountAmount = 0;
        if ($discountType === 'fixed') {
            $discountAmount = $discountValue;
        } elseif ($discountType === 'percentage') {
            $discountAmount = ($lineSubtotal * $discountValue) / 100;
        }

        // Step 5: Calculate final line total
        $lineTotal = $lineSubtotal - $discountAmount;
        $set('line_total', round($lineTotal, 2));
    }

    /**
     * Calculate service total for a service in the repeater
     */
    protected function calculateServiceTotalForRepeater(Forms\Set $set, Forms\Get $get, $livewire = null): void
    {
        $serviceId = $get('service_id');
        $enabled = $get('enabled') ?? true;
        $pricePerUnit = (float) ($get('price_per_unit') ?? 0);
        $quantity = (float) ($get('quantity') ?? 1);
        $unitType = $get('unit_type') ?? 'sqm';

        if (!$enabled || !$serviceId) {
            $set('total', 0);
            return;
        }

        // In RelationManager, ownerRecord is the Quote, not the QuoteItem
        // We need to create a temporary QuoteItem from form data for calculation
        $quoteItem = null;
        
        // Try to get the actual record being edited (if editing existing item)
        if (isset($livewire->mountedTableActionRecord) && $livewire->mountedTableActionRecord instanceof \App\Models\QuoteItem) {
            $quoteItem = $livewire->mountedTableActionRecord;
        }
        
        // If not editing, or if we need to build from form data, create temp item
        if (!$quoteItem) {
            // Get product_id from form data
            $productId = $get('../../product_id') ?? $get('../product_id') ?? $get('product_id');
            
            if ($productId) {
                $quoteItem = new \App\Models\QuoteItem();
                $quoteItem->product_id = $productId;
                
                // Get quantity and dimensions from parent form
                $quoteItem->quantity = (float) ($get('../../quantity') ?? $get('../quantity') ?? $get('quantity') ?? 1);
                $quoteItem->width_mm = $get('../../width_mm') ?? $get('../width_mm') ?? $get('width_mm');
                $quoteItem->height_mm = $get('../../height_mm') ?? $get('../height_mm') ?? $get('height_mm');
                
                // Calculate surface area and linear meter if dimensions exist
                if ($quoteItem->width_mm && $quoteItem->height_mm) {
                    $areaPerUnit = ((float) $quoteItem->width_mm * (float) $quoteItem->height_mm) / 1000000;
                    $quoteItem->surface_area_m2 = $areaPerUnit * $quoteItem->quantity;
                    $linearMeterPerUnit = (2 * ((float) $quoteItem->width_mm + (float) $quoteItem->height_mm)) / 1000;
                    $quoteItem->linear_meter = $linearMeterPerUnit * $quoteItem->quantity;
                }
            }
        }
        
        if (!$quoteItem) {
            $set('total', 0);
            return;
        }

        $serviceCalcService = app(ServiceCalculationService::class);
        $service = Service::find($serviceId);
        
        if (!$service) {
            $set('total', 0);
            return;
        }

        // Calculate service total
        $total = $serviceCalcService->calculateServiceTotal(
            $quoteItem,
            $service,
            $enabled,
            $quantity,
            $pricePerUnit
        );

        $set('total', round($total, 2));
        
        // Note: We can't update service_total on the parent form from here easily
        // because we're in a nested repeater. The total will be recalculated when the form is saved.
    }

    /**
     * Recalculate total service total from all services in the repeater
     * Note: This is called from calculateServiceTotalForRepeater, but it may not work correctly
     * in all contexts. The service total will be recalculated when the form is saved.
     */
    protected function recalculateServiceTotal($livewire): void
    {
        // In RelationManager context, we can't easily recalculate service_total here
        // because ownerRecord is the Quote, not the QuoteItem
        // The total will be recalculated when the quote item is saved
        // This method is kept for compatibility but does nothing
        return;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->sortable(),
                Tables\Columns\TextColumn::make('width_mm')
                    ->label('Width (mm)')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('height_mm')
                    ->label('Height (mm)')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('linear_meter')
                    ->label('Linear Meter (lm)')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' lm')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('product_total')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('service_total')
                    ->money('EUR')
                    ->sortable()
                    ->label('Services'),
                Tables\Columns\TextColumn::make('line_total')
                    ->money('EUR')
                    ->sortable()
                    ->label('Line Total'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        return $this->mutateFormDataBeforeSave($data);
                    })
                    ->after(function ($record) {
                        // Handle services if they were provided
                        // Check both the pendingServices property and the form data
                        $servicesData = $this->pendingServices ?? [];
                        if (!empty($servicesData)) {
                            // Validate for duplicates before saving
                            $seenServiceIds = [];
                            $validServicesData = [];
                            
                            foreach ($servicesData as $serviceData) {
                                if (!isset($serviceData['service_id']) || !$serviceData['service_id']) {
                                    continue;
                                }
                                
                                $serviceId = $serviceData['service_id'];
                                
                                // Skip duplicates
                                if (in_array($serviceId, $seenServiceIds)) {
                                    \Log::warning("Duplicate service prevented in CreateAction: service_id {$serviceId} for quote_item_id {$record->id}");
                                    continue;
                                }
                                
                                $seenServiceIds[] = $serviceId;
                                $validServicesData[] = $serviceData;
                            }
                            
                            $this->saveServices($record, $validServicesData);
                            $this->pendingServices = []; // Clear after use
                        }
                        // Recalculate totals after creating item
                        $this->recalculateItemTotals($record);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        return $this->mutateFormDataBeforeSave($data);
                    })
                    ->after(function ($record) {
                        // Handle services if they were provided
                        // Check both the pendingServices property and the form data
                        $servicesData = $this->pendingServices ?? [];
                        if (!empty($servicesData)) {
                            // Validate for duplicates before saving
                            $seenServiceIds = [];
                            $validServicesData = [];
                            
                            foreach ($servicesData as $serviceData) {
                                if (!isset($serviceData['service_id']) || !$serviceData['service_id']) {
                                    continue;
                                }
                                
                                $serviceId = $serviceData['service_id'];
                                
                                // Skip duplicates
                                if (in_array($serviceId, $seenServiceIds)) {
                                    \Log::warning("Duplicate service prevented in EditAction: service_id {$serviceId} for quote_item_id {$record->id}");
                                    continue;
                                }
                                
                                $seenServiceIds[] = $serviceId;
                                $validServicesData[] = $serviceData;
                            }
                            
                            $this->saveServices($record, $validServicesData);
                            $this->pendingServices = []; // Clear after use
                        }
                        // Recalculate totals after updating item
                        $this->recalculateItemTotals($record);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    /**
     * Save services for a quote item
     * This handles services that were added via the Repeater
     * 
     * Prevents duplicate services - each service can only be added once per quote item
     */
    protected function saveServices($quoteItem, array $servicesData): void
    {
        $serviceCalcService = app(ServiceCalculationService::class);
        
        // Track service IDs to prevent duplicates
        $seenServiceIds = [];
        $syncData = [];
        
        foreach ($servicesData as $serviceData) {
            if (!isset($serviceData['service_id']) || !$serviceData['service_id']) {
                continue;
            }
            
            $serviceId = $serviceData['service_id'];
            
            // Check for duplicates - skip if this service was already processed
            if (in_array($serviceId, $seenServiceIds)) {
                \Log::warning("Duplicate service detected: service_id {$serviceId} for quote_item_id {$quoteItem->id}. Skipping duplicate.");
                continue;
            }
            
            $seenServiceIds[] = $serviceId;
            $enabled = $serviceData['enabled'] ?? true;
            $pricePerUnit = (float) ($serviceData['price_per_unit'] ?? 0);
            $quantity = isset($serviceData['quantity']) ? (float) $serviceData['quantity'] : null;
            
            // Calculate service total
            $service = Service::find($serviceId);
            if ($service) {
                $serviceTotal = $serviceCalcService->calculateServiceTotal(
                    $quoteItem,
                    $service,
                    $enabled,
                    $quantity,
                    $pricePerUnit
                );
                
                // Get default quantity if not provided
                if ($quantity === null) {
                    $quantity = $serviceCalcService->getDefaultQuantity($quoteItem, $service) ?? 1.0;
                }
                
                $syncData[$serviceId] = [
                    'enabled' => $enabled,
                    'price_per_unit' => $pricePerUnit,
                    'quantity' => $quantity,
                    'total' => $serviceTotal,
                ];
            }
        }
        
        // Sync services to the quote item
        // The database primary key will also enforce uniqueness as a safety net
        $quoteItem->services()->sync($syncData);
    }

    /**
     * Recalculate all totals for a quote item after services are updated
     */
    protected function recalculateItemTotals($quoteItem): void
    {
        $serviceCalcService = app(ServiceCalculationService::class);
        $pricingService = app(\App\Services\QuotePricingService::class);
        
        // Recalculate item totals
        $itemTotals = $pricingService->calculateItemTotal($quoteItem);
        $quoteItem->update($itemTotals);
        
        // Recalculate quote totals
        $quote = $quoteItem->quote;
        if ($quote) {
            $quoteTotals = $pricingService->calculateQuoteTotal($quote);
            $quote->update($quoteTotals);
        }
    }
}
