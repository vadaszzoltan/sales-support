<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
        
        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                {{ __('filament.actions.save_changes') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
