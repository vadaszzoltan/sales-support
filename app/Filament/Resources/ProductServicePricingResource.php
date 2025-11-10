<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductServicePricingResource\Pages;
use App\Models\ProductServicePricing;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductServicePricingResource extends Resource
{
    protected static ?string $model = ProductServicePricing::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-euro';
    
    protected static ?string $navigationLabel = 'Product-Service Pricing';
    
    protected static ?string $modelLabel = 'Pricing Rule';
    
    protected static ?string $pluralModelLabel = 'Product-Service Pricing';
    
    protected static ?string $navigationGroup = 'Rules & Configuration';

    /**
     * Hide this resource from navigation.
     * Product-Service pricing is now managed directly from the Service form.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pricing Rule')
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
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('service_id', null))
                            ->native(false),
                        Forms\Components\Select::make('service_id')
                            ->label('Service')
                            ->relationship('service', 'name', 
                                fn ($query, Forms\Get $get) => $query->whereHas('products', fn ($q) => $q->where('products.id', $get('product_id')))
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Only services compatible with selected product will be shown'),
                        Forms\Components\TextInput::make('price_per_unit')
                            ->required()
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->label('Price per Unit')
                            ->helperText('Price per unit of measure (e.g., per m²)'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->searchable()
                    ->sortable()
                    ->label('Product'),
                Tables\Columns\TextColumn::make('service.name')
                    ->searchable()
                    ->sortable()
                    ->label('Service'),
                Tables\Columns\TextColumn::make('price_per_unit')
                    ->money('EUR')
                    ->sortable()
                    ->label('Price per Unit'),
                Tables\Columns\TextColumn::make('service.unit_of_measure')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'm2' => 'm²',
                        'db' => 'db',
                        'm' => 'm',
                        default => $state,
                    })
                    ->label('Unit'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_id')
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
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('service_id')
                    ->label('Service')
                    ->relationship('service', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('product.name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductServicePricings::route('/'),
            'create' => Pages\CreateProductServicePricing::route('/create'),
            'edit' => Pages\EditProductServicePricing::route('/{record}/edit'),
        ];
    }
}
