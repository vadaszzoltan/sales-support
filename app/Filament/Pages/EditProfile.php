<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class EditProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    
    protected static string $view = 'filament.pages.edit-profile';
    
    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.my_profile');
    }
    
    public function getTitle(): string
    {
        return __('filament.profile.title');
    }
    
    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.system_settings');
    }
    
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'name' => auth()->user()->name,
            'email' => auth()->user()->email,
            'locale' => auth()->user()->locale ?? config('locales.default', 'en'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('profile.personal_information'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label(__('user.name')),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true, table: 'users', column: 'email')
                            ->label(__('user.email')),
                    ])
                    ->columns(2),
                Forms\Components\Section::make(__('profile.preferences'))
                    ->schema([
                        Forms\Components\Select::make('locale')
                            ->label(__('user.locale'))
                            ->options([
                                'en' => __('language.en'),
                                'ro' => __('language.ro'),
                                'hu' => __('language.hu'),
                            ])
                            ->default(config('locales.default', 'en'))
                            ->native(false)
                            ->required()
                            ->helperText(__('user.locale_help')),
                    ]),
                Forms\Components\Section::make(__('profile.change_password'))
                    ->schema([
                        Forms\Components\TextInput::make('current_password')
                            ->label(__('profile.current_password'))
                            ->password()
                            ->requiredWith('new_password')
                            ->currentPassword()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('new_password')
                            ->label(__('profile.new_password'))
                            ->password()
                            ->rules([Password::defaults()])
                            ->dehydrated(fn ($state) => filled($state))
                            ->same('new_password_confirmation')
                            ->helperText(__('profile.password_help')),
                        Forms\Components\TextInput::make('new_password_confirmation')
                            ->label(__('profile.confirm_password'))
                            ->password()
                            ->requiredWith('new_password')
                            ->dehydrated(false),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        
        $user = auth()->user();
        
        // Validate current password if new password is provided
        if (!empty($data['new_password'])) {
            if (!Hash::check($data['current_password'] ?? '', $user->password)) {
                Notification::make()
                    ->title('Error')
                    ->body('Current password is incorrect.')
                    ->danger()
                    ->send();
                return;
            }
        }
        
        // Update basic information
        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'locale' => $data['locale'] ?? config('locales.default', 'en'),
        ];
        
        // Update password if provided
        if (!empty($data['new_password'])) {
            $updateData['password'] = Hash::make($data['new_password']);
        }
        
        $user->update($updateData);
        
        // Set the new locale immediately for the current session
        app()->setLocale($user->getPreferredLocale());
        
        // Refresh form data
        $this->form->fill([
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale ?? config('locales.default', 'en'),
        ]);
        
        Notification::make()
            ->title(__('filament.profile.updated_success'))
            ->body(__('filament.profile.updated_message'))
            ->success()
            ->send();
    }
}
