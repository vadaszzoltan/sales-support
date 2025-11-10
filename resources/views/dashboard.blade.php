<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{ __("You're logged in!") }}
                    
                    @auth
                        @if(auth()->user()->isAdmin())
                            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <h3 class="text-lg font-semibold text-blue-900 mb-2">
                                    👨‍💼 Admin Panel Access
                                </h3>
                                <p class="text-blue-800 mb-4">
                                    As an admin, you can manage users and approve new registrations through the Filament Admin Panel.
                                </p>
                                <a href="{{ url('/admin') }}" 
                                   class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                    Go to Admin Panel
                                </a>
                                <p class="text-sm text-blue-700 mt-3">
                                    <strong>Quick tip:</strong> Click "Users" in the admin panel sidebar to view and approve pending users.
                                </p>
                            </div>
                        @endif
                    @endauth
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
