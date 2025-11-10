<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    
    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.products');
    }
    
    public static function getModelLabel(): string
    {
        return __('filament.resource.product');
    }
    
    public static function getPluralModelLabel(): string
    {
        return __('filament.resource.products');
    }
    
    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_group.master_data');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Product Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Product Name'),
                        Forms\Components\TextInput::make('code')
                            ->label('Product Code / SKU')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText('Optional product code'),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Pricing & Settings')
                    ->schema([
                        Forms\Components\TextInput::make('base_price')
                            ->required()
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->label('Base Price'),
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
                        Forms\Components\Toggle::make('is_combined')
                            ->label('Combined Product')
                            ->helperText('Is this a combined product (e.g., Float6+Float6)?')
                            ->default(false),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
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
                Tables\Columns\TextColumn::make('base_price')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit_of_measure')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'm2' => 'm²',
                        'db' => 'db',
                        'm' => 'm',
                        default => $state,
                    })
                    ->label('Unit'),
                Tables\Columns\IconColumn::make('is_combined')
                    ->boolean()
                    ->label('Combined')
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
                    ->placeholder('All products')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
                Tables\Filters\TernaryFilter::make('is_combined')
                    ->label('Product Type')
                    ->placeholder('All products')
                    ->trueLabel('Combined only')
                    ->falseLabel('Simple only'),
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
            // Relation managers can be added later for managing compatibility and pricing
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
