<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SystemSettingResource\Pages;
use App\Models\SystemSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SystemSettingResource extends Resource
{
    protected static ?string $model = SystemSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.system_settings');
    }
    
    protected static ?string $modelLabel = 'System Setting';
    
    protected static ?string $pluralModelLabel = 'System Settings';
    
    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.system_settings');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Setting Information')
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn ($record) => $record !== null)
                            ->helperText('Setting key (e.g., delivery_fee_per_km)'),
                        Forms\Components\Select::make('type')
                            ->required()
                            ->options([
                                'string' => 'String',
                                'decimal' => 'Decimal',
                                'integer' => 'Integer',
                                'boolean' => 'Boolean',
                                'json' => 'JSON Array',
                            ])
                            ->default('string')
                            ->native(false)
                            ->reactive(),
                        Forms\Components\Textarea::make('value')
                            ->required()
                            ->rows(3)
                            ->visible(fn (Forms\Get $get) => in_array($get('type'), ['string', 'json']))
                            ->helperText('For JSON type, use array format: ["draft", "sent", "accepted"]'),
                        Forms\Components\TextInput::make('value')
                            ->required()
                            ->numeric()
                            ->step(0.01)
                            ->visible(fn (Forms\Get $get) => $get('type') === 'decimal'),
                        Forms\Components\TextInput::make('value')
                            ->required()
                            ->numeric()
                            ->visible(fn (Forms\Get $get) => $get('type') === 'integer'),
                        Forms\Components\Toggle::make('value')
                            ->required()
                            ->visible(fn (Forms\Get $get) => $get('type') === 'boolean')
                            ->formatStateUsing(fn ($state) => $state ? 'true' : 'false')
                            ->dehydrateStateUsing(fn ($state) => $state ? 'true' : 'false'),
                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->label('Setting Key'),
                Tables\Columns\TextColumn::make('description')
                    ->wrap()
                    ->limit(50),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'decimal', 'integer' => 'info',
                        'boolean' => 'success',
                        'json' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('value')
                    ->wrap()
                    ->limit(50)
                    ->label('Current Value'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'string' => 'String',
                        'decimal' => 'Decimal',
                        'integer' => 'Integer',
                        'boolean' => 'Boolean',
                        'json' => 'JSON',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // No bulk actions - settings should be managed individually
            ])
            ->defaultSort('key');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemSettings::route('/'),
            'create' => Pages\CreateSystemSetting::route('/create'),
            'edit' => Pages\EditSystemSetting::route('/{record}/edit'),
        ];
    }
}
