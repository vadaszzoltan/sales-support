<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccessoryResource\Pages;
use App\Models\Accessory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AccessoryResource extends Resource
{
    protected static ?string $model = Accessory::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';
    
    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.accessories');
    }
    
    public static function getModelLabel(): string
    {
        return __('filament.resource.accessory');
    }
    
    public static function getPluralModelLabel(): string
    {
        return __('filament.resource.accessories');
    }
    
    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_group.master_data');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Accessory Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Accessory Name')
                            ->helperText('e.g., Hinge, Handle, LED Lighting'),
                        Forms\Components\TextInput::make('code')
                            ->label('Accessory Code')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText('Optional accessory code'),
                        Forms\Components\TextInput::make('uniform_price')
                            ->required()
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->label('Uniform Price')
                            ->helperText('Same price regardless of product'),
                        Forms\Components\Select::make('unit_of_measure')
                            ->required()
                            ->options([
                                'db' => 'Piece (db)',
                                'm' => 'Meter (m)',
                                'm2' => 'Square Meter (m²)',
                            ])
                            ->default('db')
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
                Tables\Columns\TextColumn::make('uniform_price')
                    ->money('EUR')
                    ->sortable()
                    ->label('Price'),
                Tables\Columns\TextColumn::make('unit_of_measure')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'm2' => 'm²',
                        'db' => 'db',
                        'm' => 'm',
                        default => $state,
                    })
                    ->label('Unit'),
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
                    ->placeholder('All accessories')
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
            // Compatibility relations can be managed from Product resource
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccessories::route('/'),
            'create' => Pages\CreateAccessory::route('/create'),
            'view' => Pages\ViewAccessory::route('/{record}'),
            'edit' => Pages\EditAccessory::route('/{record}/edit'),
        ];
    }
}
