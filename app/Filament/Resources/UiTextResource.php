<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UiTextResource\Pages;
use App\Helpers\TranslationHelper;
use App\Models\UiText;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class UiTextResource extends Resource
{
    protected static ?string $model = UiText::class;

    protected static ?string $navigationIcon = 'heroicon-o-language';
    
    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.ui_texts');
    }
    
    protected static ?string $modelLabel = 'UI Text';
    
    protected static ?string $pluralModelLabel = 'UI Texts';
    
    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.system_settings');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Translation Key')
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn ($record) => $record !== null)
                            ->helperText('Unique key identifier (e.g., quote.status.draft). Cannot be changed after creation.')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('Optional description of what this translation is used for.'),
                    ]),
                Forms\Components\Section::make('Translations')
                    ->description('Enter translations for all supported languages. Leave empty to use fallback.')
                    ->schema([
                        Forms\Components\Tabs::make('Languages')
                            ->tabs([
                                Forms\Components\Tabs\Tab::make('English (en)')
                                    ->schema([
                                        Forms\Components\Textarea::make('value_en')
                                            ->label('English Translation')
                                            ->rows(4)
                                            ->columnSpanFull()
                                            ->helperText('English translation (base/default language)'),
                                    ]),
                                Forms\Components\Tabs\Tab::make('Romanian (ro)')
                                    ->schema([
                                        Forms\Components\Textarea::make('value_ro')
                                            ->label('Romanian Translation')
                                            ->rows(4)
                                            ->columnSpanFull()
                                            ->helperText('Romanian translation (used in PDFs)'),
                                    ]),
                                Forms\Components\Tabs\Tab::make('Hungarian (hu)')
                                    ->schema([
                                        Forms\Components\Textarea::make('value_hu')
                                            ->label('Hungarian Translation')
                                            ->rows(4)
                                            ->columnSpanFull()
                                            ->helperText('Hungarian translation'),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->label('Key')
                    ->copyable()
                    ->copyMessage('Key copied!'),
                Tables\Columns\TextColumn::make('value_en')
                    ->label('English')
                    ->limit(30)
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('value_ro')
                    ->label('Romanian')
                    ->limit(30)
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('value_hu')
                    ->label('Hungarian')
                    ->limit(30)
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(40)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
            ->defaultSort('key');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUiTexts::route('/'),
            'create' => Pages\CreateUiText::route('/create'),
            'edit' => Pages\EditUiText::route('/{record}/edit'),
        ];
    }
}
