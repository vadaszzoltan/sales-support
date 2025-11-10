<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    
    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.users');
    }
    
    public static function getModelLabel(): string
    {
        return __('filament.resource.user');
    }
    
    public static function getPluralModelLabel(): string
    {
        return __('filament.resource.users');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\Select::make('role')
                    ->required()
                    ->options([
                        'admin' => 'Admin',
                        'sales_agent' => 'Sales Agent',
                    ])
                    ->default('sales_agent')
                    ->native(false),
                Forms\Components\Toggle::make('approved')
                    ->label('Approved')
                    ->helperText('Only approved users can log in')
                    ->default(false),
                Forms\Components\Select::make('locale')
                    ->label(__('user.locale'))
                    ->options([
                        'en' => __('language.en'),
                        'ro' => __('language.ro'),
                        'hu' => __('language.hu'),
                    ])
                    ->default(config('locales.default', 'en'))
                    ->native(false)
                    ->helperText(__('user.locale_help')),
                Forms\Components\DateTimePicker::make('email_verified_at'),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required(fn ($livewire) => $livewire instanceof \App\Filament\Resources\UserResource\Pages\CreateUser)
                    ->maxLength(255)
                    ->dehydrated(fn ($state) => filled($state))
                    ->helperText(fn ($livewire) => $livewire instanceof \App\Filament\Resources\UserResource\Pages\EditUser ? 'Leave blank to keep current password' : 'Minimum 8 characters')
                    ->minLength(8)
                    ->visible(fn ($livewire) => $livewire instanceof \App\Filament\Resources\UserResource\Pages\CreateUser || $livewire instanceof \App\Filament\Resources\UserResource\Pages\EditUser),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'sales_agent' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'admin' => 'Admin',
                        'sales_agent' => 'Sales Agent',
                        default => $state,
                    })
                    ->searchable(),
                Tables\Columns\IconColumn::make('approved')
                    ->boolean()
                    ->label('Approved')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('locale')
                    ->label('Language')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'en' => 'English',
                        'ro' => 'Română',
                        'hu' => 'Magyar',
                        default => 'Default',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'en' => 'info',
                        'ro' => 'warning',
                        'hu' => 'success',
                        default => 'gray',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'sales_agent' => 'Sales Agent',
                    ]),
                Tables\Filters\TernaryFilter::make('approved')
                    ->label('Approval Status')
                    ->placeholder('All users')
                    ->trueLabel('Approved only')
                    ->falseLabel('Pending approval only')
                    ->queries(
                        true: fn (Builder $query) => $query->where('approved', true),
                        false: fn (Builder $query) => $query->where('approved', false),
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $record->update(['approved' => true]);
                    })
                    ->visible(fn (User $record) => !$record->approved),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $record->update(['approved' => false]);
                    })
                    ->visible(fn (User $record) => $record->approved),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
