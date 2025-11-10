<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductServiceCompatibilityResource\Pages;
use App\Models\Product;
use App\Models\ProductServiceCompatibility;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductServiceCompatibilityResource extends Resource
{
    protected static ?string $model = ProductServiceCompatibility::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';
    
    public static function getNavigationLabel(): string
    {
        return 'Product-Service Compatibility';
    }
    
    public static function getModelLabel(): string
    {
        return 'Compatibility Rule';
    }
    
    public static function getPluralModelLabel(): string
    {
        return 'Product-Service Compatibility';
    }
    
    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_group.rules_configuration');
    }

    /**
     * Hide this resource from navigation.
     * Product-Service compatibility is now managed directly from the Service form.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Compatibility Rule')
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
                            ->native(false),
                        Forms\Components\Select::make('service_id')
                            ->label('Service')
                            ->relationship('service', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Select which service can be linked to this product'),
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
                    ->getOptionLabelUsing(fn ($value): ?string => Product::find($value)?->name)
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
            'index' => Pages\ListProductServiceCompatibilities::route('/'),
            'create' => Pages\CreateProductServiceCompatibility::route('/create'),
            'edit' => Pages\EditProductServiceCompatibility::route('/{record}/edit'),
        ];
    }
}
