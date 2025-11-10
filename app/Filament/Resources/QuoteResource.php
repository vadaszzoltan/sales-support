<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuoteResource\Pages;
use App\Filament\Resources\QuoteResource\RelationManagers;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductServicePricing;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use App\Services\QuoteDuplicateService;
use App\Services\QuotePdfService;
use App\Services\QuotePricingService;
use App\Services\ServiceCalculationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuoteResource extends Resource
{
    protected static ?string $model = Quote::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.quotes');
    }
    
    public static function getModelLabel(): string
    {
        return __('filament.resource.quote');
    }
    
    public static function getPluralModelLabel(): string
    {
        return __('filament.resource.quotes');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Quote Information')
                    ->schema([
                        Forms\Components\TextInput::make('quote_number')
                            ->label('Quote Number')
                            ->disabled()
                            ->dehydrated(false)
                            ->hidden(fn ($livewire) => $livewire instanceof \App\Filament\Resources\QuoteResource\Pages\CreateQuote)
                            ->helperText('Auto-generated')
                            ->visible(fn ($livewire) => !($livewire instanceof \App\Filament\Resources\QuoteResource\Pages\CreateQuote)),
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->email(),
                                Forms\Components\TextInput::make('phone'),
                            ])
                            ->native(false),
                        Forms\Components\Select::make('user_id')
                            ->label('Created By')
                            ->relationship('user', 'name')
                            ->default(fn () => auth()->id())
                            ->required()
                            ->disabled()
                            ->native(false),
                        Forms\Components\Select::make('status')
                            ->label(__('filament.quote.status'))
                            ->options([
                                'draft' => __('filament.quote.status.draft'),
                                'sent' => __('filament.quote.status.sent'),
                                'under_review' => __('filament.quote.status.under_review'),
                                'accepted' => __('filament.quote.status.accepted'),
                                'rejected' => __('filament.quote.status.rejected'),
                                'closed' => __('filament.quote.status.closed'),
                            ])
                            ->default('draft')
                            ->required()
                            ->native(false),
                        Forms\Components\DatePicker::make('quote_date')
                            ->required()
                            ->default(now())
                            ->label('Quote Date'),
                        Forms\Components\DatePicker::make('valid_until')
                            ->label('Valid Until'),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Delivery & Installation')
                    ->schema([
                        Forms\Components\TextInput::make('delivery_distance_km')
                            ->label('Delivery Distance (km)')
                            ->numeric()
                            ->step(0.01)
                            ->reactive()
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                // This would be calculated by a service, but for now just store the value
                                $set('delivery_cost', $state * 1.5) // Placeholder calculation
                            ),
                        Forms\Components\TextInput::make('delivery_cost')
                            ->label('Delivery Cost')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('installation_multiplier_override')
                            ->label('Installation Multiplier Override (%)')
                            ->numeric()
                            ->step(0.01)
                            ->helperText('Leave empty to use system default'),
                        Forms\Components\TextInput::make('installation_cost')
                            ->label('Installation Cost')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Discount & VAT')
                    ->schema([
                        Forms\Components\Select::make('discount_type')
                            ->options([
                                'none' => 'No Discount',
                                'fixed' => 'Fixed Amount',
                                'percentage' => 'Percentage',
                            ])
                            ->default('none')
                            ->reactive()
                            ->native(false),
                        Forms\Components\TextInput::make('discount_value')
                            ->label('Discount Value')
                            ->numeric()
                            ->step(0.01)
                            ->default(0)
                            ->visible(fn (Forms\Get $get) => $get('discount_type') !== 'none')
                            ->required(fn (Forms\Get $get) => $get('discount_type') !== 'none'),
                        Forms\Components\TextInput::make('vat_rate')
                            ->label('VAT Rate (%)')
                            ->numeric()
                            ->step(0.01)
                            ->default(27)
                            ->required(),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Totals (Read-Only)')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->prefix('€')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('total_discount')
                            ->label('Total Discount')
                            ->prefix('€')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('vat_amount')
                            ->label('VAT Amount')
                            ->prefix('€')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Grand Total')
                            ->prefix('€')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                // Items Repeater - allows adding items BEFORE saving the quote
                // This is only shown when creating a new quote (not editing)
                Forms\Components\Section::make('Quote Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('Items')
                            ->relationship('items')
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
                                    ->getOptionLabelUsing(fn ($value): ?string => Product::find($value)?->name)
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                $set('unit_price', $product->base_price);
                                                // Trigger calculation
                                                static::calculateItemTotals($set, $get);
                                            }
                                        }
                                    })
                                    ->native(false)
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('quantity')
                                    ->required()
                                    ->numeric()
                                    ->step(0.01)
                                    ->default(1)
                                    ->reactive()
                                    ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                        static::calculateItemTotals($set, $get)
                                    )
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('width_mm')
                                    ->label('Width (mm)')
                                    ->numeric()
                                    ->reactive()
                                    ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                        static::calculateItemTotals($set, $get)
                                    )
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('height_mm')
                                    ->label('Height (mm)')
                                    ->numeric()
                                    ->reactive()
                                    ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                        static::calculateItemTotals($set, $get)
                                    )
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Unit Price (€/m²)')
                                    ->numeric()
                                    ->prefix('€')
                                    ->step(0.01)
                                    ->required()
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                        static::calculateItemTotals($set, $get)
                                    )
                                    ->helperText('Price per square meter')
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('product_total')
                                    ->label('Product Total')
                                    ->numeric()
                                    ->prefix('€')
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('line_total')
                                    ->label('Line Total')
                                    ->numeric()
                                    ->prefix('€')
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('linear_meter')
                                    ->label('Linear Meter (lm)')
                                    ->numeric()
                                    ->step(0.01)
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('Auto-calculated: 2 × (width + height) / 1000 × quantity')
                                    ->columnSpan(1),
                                // Services Repeater - nested within item
                                Forms\Components\Repeater::make('services')
                                    ->relationship('services')
                                    ->label('Services')
                                    ->schema([
                                        Forms\Components\Select::make('service_id')
                                            ->label('Service')
                                            ->relationship('service', 'name', function ($query, Forms\Get $get) {
                                                // Get product_id from parent item
                                                $productId = $get('../../product_id');
                                                if ($productId) {
                                                    return $query->whereHas('productPricing', function ($q) use ($productId) {
                                                        $q->where('product_id', $productId);
                                                    });
                                                }
                                                return $query->whereRaw('1 = 0'); // No services if no product
                                            })
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                                if ($state) {
                                                    $productId = $get('../../product_id');
                                                    if ($productId) {
                                                        $pricing = ProductServicePricing::where('product_id', $productId)
                                                            ->where('service_id', $state)
                                                            ->first();
                                                        if ($pricing) {
                                                            $set('price_per_unit', $pricing->price_per_unit);
                                                            $set('unit_type', $pricing->unit_type ?? 'sqm');
                                                            
                                                            // Set default quantity
                                                            $serviceCalcService = app(ServiceCalculationService::class);
                                                            $service = Service::find($state);
                                                            if ($service) {
                                                                // Create temp item for calculation
                                                                $tempItem = new \App\Models\QuoteItem();
                                                                $tempItem->product_id = $productId;
                                                                $tempItem->quantity = (float) ($get('../../quantity') ?? 1);
                                                                $tempItem->width_mm = $get('../../width_mm');
                                                                $tempItem->height_mm = $get('../../height_mm');
                                                                if ($tempItem->width_mm && $tempItem->height_mm) {
                                                                    $areaPerUnit = ((float) $tempItem->width_mm * (float) $tempItem->height_mm) / 1000000;
                                                                    $tempItem->surface_area_m2 = $areaPerUnit * $tempItem->quantity;
                                                                    $linearMeterPerUnit = (2 * ((float) $tempItem->width_mm + (float) $tempItem->height_mm)) / 1000;
                                                                    $tempItem->linear_meter = $linearMeterPerUnit * $tempItem->quantity;
                                                                }
                                                                
                                                                $defaultQty = $serviceCalcService->getDefaultQuantity($tempItem, $service);
                                                                if ($defaultQty !== null) {
                                                                    $set('quantity', $defaultQty);
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            })
                                            ->native(false),
                                        Forms\Components\Toggle::make('enabled')
                                            ->label('Enabled')
                                            ->default(true)
                                            ->reactive(),
                                        Forms\Components\TextInput::make('price_per_unit')
                                            ->label('Unit Price')
                                            ->numeric()
                                            ->prefix('€')
                                            ->step(0.01)
                                            ->required(),
                                        Forms\Components\TextInput::make('quantity')
                                            ->label('Quantity')
                                            ->numeric()
                                            ->step(0.01)
                                            ->default(1)
                                            ->visible(function (Forms\Get $get) {
                                                $unitType = $get('../../unit_type') ?? 'sqm';
                                                return in_array($unitType, ['piece', 'sqm']);
                                            }),
                                        Forms\Components\TextInput::make('total')
                                            ->label('Total')
                                            ->numeric()
                                            ->prefix('€')
                                            ->disabled()
                                            ->dehydrated(),
                                        Forms\Components\Hidden::make('unit_type'),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => 
                                        $state['service_id'] 
                                            ? Service::find($state['service_id'])?->name 
                                            : 'New Service'
                                    )
                                    ->visible(function (Forms\Get $get) {
                                        // Get product_id from parent item (go up two levels: service -> item -> product_id)
                                        $productId = $get('../../product_id');
                                        return (bool) $productId;
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->minItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => 
                                $state['product_id'] 
                                    ? Product::find($state['product_id'])?->name 
                                    : 'New Item'
                            )
                            ->visible(fn ($livewire) => $livewire instanceof \App\Filament\Resources\QuoteResource\Pages\CreateQuote),
                    ])
                    ->visible(fn ($livewire) => $livewire instanceof \App\Filament\Resources\QuoteResource\Pages\CreateQuote),
            ]);
    }

    /**
     * Calculate item totals using area-based pricing
     * This is a static helper method that can be used in form field callbacks
     */
    public static function calculateItemTotals(Forms\Set $set, Forms\Get $get): void
    {
        $quantity = (float) ($get('quantity') ?? 1);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $widthMm = $get('width_mm');
        $heightMm = $get('height_mm');

        // Calculate area per unit (m²)
        $areaPerUnit = null;
        if ($widthMm && $heightMm && $widthMm > 0 && $heightMm > 0) {
            $areaPerUnit = ((float) $widthMm * (float) $heightMm) / 1000000;
        }

        // Calculate linear meter per unit (lm)
        // Formula: 2 × (width_mm + height_mm) / 1000
        $linearMeterPerUnit = null;
        if ($widthMm && $heightMm && $widthMm > 0 && $heightMm > 0) {
            $linearMeterPerUnit = (2 * ((float) $widthMm + (float) $heightMm)) / 1000;
            // Store total linear meter (perimeter per unit × quantity)
            $set('linear_meter', round($linearMeterPerUnit * $quantity, 2));
        } else {
            $set('linear_meter', null);
        }

        // Calculate product total
        if ($areaPerUnit !== null && $areaPerUnit > 0) {
            $productTotal = $areaPerUnit * $unitPrice * $quantity;
        } else {
            $productTotal = $unitPrice * $quantity;
        }

        $set('product_total', round($productTotal, 2));
        
        // For now, line_total = product_total (services/accessories can be added later via RelationManager)
        $set('line_total', round($productTotal, 2));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('quote_number')
                    ->searchable()
                    ->sortable()
                    ->label(__('filament.quote.quote_number'))
                    ->copyable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable()
                    ->label(__('filament.quote.customer')),
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('filament.quote.created_by'))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label(__('filament.quote.status'))
                    ->formatStateUsing(fn (string $state): string => __('filament.quote.status.' . $state))
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'sent' => 'info',
                        'under_review' => 'warning',
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        'closed' => 'secondary',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('quote_date')
                    ->label(__('filament.quote.quote_date'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('EUR')
                    ->sortable()
                    ->label(__('filament.quote.total')),
                Tables\Columns\TextColumn::make('version')
                    ->badge()
                    ->label(__('filament.quote.version'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('filament.quote.status'))
                    ->options([
                        'draft' => __('filament.quote.status.draft'),
                        'sent' => __('filament.quote.status.sent'),
                        'under_review' => __('filament.quote.status.under_review'),
                        'accepted' => __('filament.quote.status.accepted'),
                        'rejected' => __('filament.quote.status.rejected'),
                        'closed' => __('filament.quote.status.closed'),
                    ]),
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('quote_date')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Quote Date From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Quote Date Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn ($query, $date) => $query->whereDate('quote_date', '>=', $date))
                            ->when($data['created_until'], fn ($query, $date) => $query->whereDate('quote_date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('generatePdf')
                    ->label('Generate PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->action(function (Quote $record) {
                        // Recalculate totals to ensure PDF has accurate data
                        $pricingService = app(QuotePricingService::class);
                        $record = $pricingService->recalculateAndSave($record);
                        
                        // Generate PDF
                        $pdfService = app(QuotePdfService::class);
                        $pdfService->generatePdf($record);
                        
                        // Refresh record to get updated PDF info
                        $record->refresh();
                        
                        // Show success notification with download link
                        $pdfUrl = $pdfService->getDownloadUrl($record);
                        
                        Notification::make()
                            ->title('PDF generated successfully!')
                            ->body('The quote PDF has been generated.')
                            ->success()
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('download')
                                    ->label('Download PDF')
                                    ->url($pdfUrl, shouldOpenInNewTab: true)
                                    ->button(),
                            ])
                            ->send();
                    }),
                Tables\Actions\Action::make('duplicate')
                    ->label('Create Version')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Create New Version of Quote')
                    ->modalDescription('This will create a new version of this quote with all items copied. The original quote will remain unchanged as a historical record.')
                    ->action(function (Quote $record) {
                        // Use the QuoteDuplicateService to create a new version
                        // This implements UC-S-02 from the SRS: "Modify an existing quote by creating
                        // a new version (copy), keeping all items from the previous version"
                        $quoteDuplicateService = app(QuoteDuplicateService::class);
                        $newQuote = $quoteDuplicateService->duplicateWithItems($record);
                        
                        // Show success notification
                        Notification::make()
                            ->title('New version created')
                            ->body("Quote version {$newQuote->quote_number} has been created with all items copied.")
                            ->success()
                            ->send();
                        
                        // Redirect to the edit page of the new quote version
                        // The Sales user can now modify quantities, items, etc. without affecting the original
                        return redirect()->to(
                            QuoteResource::getUrl('edit', ['record' => $newQuote])
                        );
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('quote_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuotes::route('/'),
            'create' => Pages\CreateQuote::route('/create'),
            'view' => Pages\ViewQuote::route('/{record}'),
            'edit' => Pages\EditQuote::route('/{record}/edit'),
        ];
    }
}
