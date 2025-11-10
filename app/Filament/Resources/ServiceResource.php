<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Product;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    
    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.services');
    }
    
    public static function getModelLabel(): string
    {
        return __('filament.resource.service');
    }
    
    public static function getPluralModelLabel(): string
    {
        return __('filament.resource.services');
    }
    
    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_group.master_data');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Service Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Service Name')
                            ->helperText('e.g., Cutting, Drilling, Polishing, Tempering'),
                        Forms\Components\TextInput::make('code')
                            ->label('Service Code')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText('Optional service code'),
                        Forms\Components\Select::make('pricing_mode')
                            ->required()
                            ->options([
                                Service::PRICING_PER_SQM => 'Per square meter (m²)',
                                Service::PRICING_PER_LM => 'Per linear meter (lm)',
                                Service::PRICING_PER_PIECE => 'Per piece',
                            ])
                            ->default(Service::PRICING_PER_SQM)
                            ->native(false)
                            ->label('Pricing Mode')
                            ->helperText('How should this service be calculated?')
                            ->reactive(),
                        Forms\Components\Select::make('unit_of_measure')
                            ->required()
                            ->options([
                                'm2' => 'Square Meter (m²)',
                                'db' => 'Piece (db)',
                                'm' => 'Meter (m)',
                            ])
                            ->default('m2')
                            ->native(false)
                            ->label('Unit of Measure'),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Product Prices')
                    ->schema([
                        Forms\Components\Repeater::make('productPricing')
                            ->relationship('productPricing')
                            ->label('Product Prices')
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
                                    ->native(false),
                                Forms\Components\TextInput::make('price_per_unit')
                                    ->label('Unit Price')
                                    ->numeric()
                                    ->prefix('€')
                                    ->step(0.01)
                                    ->required()
                                    ->helperText(function (Forms\Get $get) {
                                        // Get pricing_mode from the parent form (go up two levels: price_per_unit -> productPricing -> service)
                                        $pricingMode = $get('../../pricing_mode') ?? Service::PRICING_PER_SQM;
                                        return match($pricingMode) {
                                            Service::PRICING_PER_SQM => 'Price per m²',
                                            Service::PRICING_PER_LM => 'Price per linear meter',
                                            Service::PRICING_PER_PIECE => 'Price per piece',
                                            default => 'Price per unit',
                                        };
                                    }),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => 
                                $state['product_id'] 
                                    ? Product::find($state['product_id'])?->name 
                                    : 'New Product Price'
                            )
                            ->addActionLabel('Add Product Price')
                            ->deleteAction(
                                fn ($action) => $action->label('Remove Product')
                            )
                            ->reorderable(false),
                    ])
                    ->collapsible()
                    ->description('Define which products this service applies to and set the price for each product.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('pricing_mode')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Service::PRICING_PER_SQM => 'Per m²',
                        Service::PRICING_PER_LM => 'Per lm',
                        Service::PRICING_PER_PIECE => 'Per piece',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        Service::PRICING_PER_SQM => 'info',
                        Service::PRICING_PER_LM => 'warning',
                        Service::PRICING_PER_PIECE => 'success',
                        default => 'gray',
                    })
                    ->label('Pricing Mode')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit_of_measure')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'm2' => 'm²',
                        'db' => 'db',
                        'm' => 'm',
                        default => $state,
                    })
                    ->label('Unit')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All services')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            // Compatibility and pricing relations can be managed from Product resource
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'view' => Pages\ViewService::route('/{record}'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
