<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div x-data="{ show: false }">
            <x-input-label for="password" :value="__('Password')" />

            <div class="auth-input-wrap">
                <x-text-input id="password" class="block mt-1 w-full"
                                type="password"
                                ::type="show ? 'text' : 'password'"
                                name="password"
                                style="padding-right: 44px;"
                                required autocomplete="current-password" />
                <x-password-toggle />
            </div>

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end mt-4">
            <x-primary-button>
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
