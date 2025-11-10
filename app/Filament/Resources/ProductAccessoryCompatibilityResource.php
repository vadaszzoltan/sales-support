<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductAccessoryCompatibilityResource\Pages;
use App\Models\ProductAccessoryCompatibility;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductAccessoryCompatibilityResource extends Resource
{
    protected static ?string $model = ProductAccessoryCompatibility::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';
    
    public static function getNavigationLabel(): string
    {
        return __('filament.navigation_group.product_accessory_compatibility');
    }
    
    public static function getModelLabel(): string
    {
        return 'Compatibility Rule';
    }
    
    public static function getPluralModelLabel(): string
    {
        return __('filament.navigation_group.product_accessory_compatibility');
    }
    
    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_group.rules_configuration');
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
                            ->getOptionLabelUsing(fn ($value): ?string => \App\Models\Product::find($value)?->name)
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Forms\Components\Select::make('accessory_id')
                            ->label('Accessory')
                            ->relationship('accessory', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Select which accessory can be linked to this product'),
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
                Tables\Columns\TextColumn::make('accessory.name')
                    ->searchable()
                    ->sortable()
                    ->label('Accessory'),
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
                Tables\Filters\SelectFilter::make('accessory_id')
                    ->label('Accessory')
                    ->relationship('accessory', 'name')
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
            'index' => Pages\ListProductAccessoryCompatibilities::route('/'),
            'create' => Pages\CreateProductAccessoryCompatibility::route('/create'),
            'edit' => Pages\EditProductAccessoryCompatibility::route('/{record}/edit'),
        ];
    }
}
