<x-guest-layout>

    <div class="min-h-screen flex flex-col sm:justify-center items-center py-10 bg-[#0F172A]">
        
        <div class="mb-8 text-center mt-6">
            <div class="text-4xl font-poppins font-bold tracking-widest text-white flex items-baseline justify-center">
                TRUST<span class="text-[#C9A84C]">A</span>
            </div>
            <div class="h-0.5 w-full bg-[#14B8A6] mt-2 mb-2"></div>
            <span class="text-xs uppercase tracking-[0.2em] text-gray-400">Trusted Accounting Solution</span>
        </div>

        <div class="w-full sm:max-w-md mt-2 px-8 py-10 bg-[#1E293B] shadow-2xl overflow-hidden sm:rounded-2xl border border-gray-800">
            <h2 class="text-xl font-poppins font-semibold text-white mb-6 text-center">Create your account</h2>
            
            <form method="POST" action="{{ route('register') }}" x-data="{ showPassword: false, showConfirm: false }">
                @csrf

                <!-- Company Name -->
                <div>
                    <label for="company_name" class="block text-sm font-medium text-gray-300">Company / Business Name <span class="text-red-400">*</span></label>
                    <input id="company_name" class="block mt-1 w-full bg-gray-800 border-gray-700 text-white focus:border-[#14B8A6] focus:ring-[#14B8A6] rounded-md shadow-sm" type="text" name="company_name" value="{{ old('company_name') }}" required autofocus />
                    <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
                </div>

                <!-- Admin Name -->
                <div class="mt-4">
                    <label for="name" class="block text-sm font-medium text-gray-300">Your Full Name <span class="text-red-400">*</span></label>
                    <input id="name" class="block mt-1 w-full bg-gray-800 border-gray-700 text-white focus:border-[#14B8A6] focus:ring-[#14B8A6] rounded-md shadow-sm" type="text" name="name" value="{{ old('name') }}" required autocomplete="name" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <!-- Email Address -->
                <div class="mt-4">
                    <label for="email" class="block text-sm font-medium text-gray-300">Email Address <span class="text-red-400">*</span></label>
                    <input id="email" class="block mt-1 w-full bg-gray-800 border-gray-700 text-white focus:border-[#14B8A6] focus:ring-[#14B8A6] rounded-md shadow-sm" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="mt-4 relative">
                    <label for="password" class="block text-sm font-medium text-gray-300">Password <span class="text-red-400">*</span></label>
                    <div style="position: relative;">
                        <input id="password" class="block mt-1 w-full bg-gray-800 border-gray-700 text-white focus:border-[#14B8A6] focus:ring-[#14B8A6] rounded-md shadow-sm" style="padding-right: 2.5rem;"
                                        type="password"
                                        name="password"
                                        required autocomplete="new-password" />
                        <button type="button" style="position: absolute; right: 0; top: 0; bottom: 0; padding-right: 0.75rem; display: flex; align-items: center; color: #9ca3af; background: transparent; border: none; cursor: pointer;" onclick="togglePassword('password', 'eye-password')">
                            <svg id="eye-password" style="width: 1.25rem; height: 1.25rem;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <!-- Eye Closed (default) -->
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-gray-400">Password must be at least 8 characters, with letters, numbers, and symbols.</p>
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="mt-4 relative">
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-300">Confirm Password <span class="text-red-400">*</span></label>
                    <div style="position: relative;">
                        <input id="password_confirmation" class="block mt-1 w-full bg-gray-800 border-gray-700 text-white focus:border-[#14B8A6] focus:ring-[#14B8A6] rounded-md shadow-sm" style="padding-right: 2.5rem;"
                                        type="password"
                                        name="password_confirmation" required autocomplete="new-password" />
                        <button type="button" style="position: absolute; right: 0; top: 0; bottom: 0; padding-right: 0.75rem; display: flex; align-items: center; color: #9ca3af; background: transparent; border: none; cursor: pointer;" onclick="togglePassword('password_confirmation', 'eye-confirm')">
                            <svg id="eye-confirm" style="width: 1.25rem; height: 1.25rem;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <!-- Eye Closed (default) -->
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between mt-8">
                    <a class="text-sm text-[#14B8A6] hover:text-white rounded-md focus:outline-none transition-colors" href="{{ route('login') }}">
                        Already registered?
                    </a>

                    <button type="submit" class="inline-flex justify-center items-center px-6 py-2.5 bg-[#14B8A6] border border-transparent rounded-lg font-semibold text-xs text-[#0F172A] uppercase tracking-widest hover:bg-teal-400 focus:bg-teal-400 active:bg-teal-500 focus:outline-none transition ease-in-out duration-150 shadow-lg shadow-teal-900/50">
                        Register
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePassword(inputId, iconId) {
            var input = document.getElementById(inputId);
            var icon = document.getElementById(iconId);
            if (input.type === "password") {
                input.type = "text";
                // Eye Open
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
            } else {
                input.type = "password";
                // Eye Closed
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
            }
        }
    </script>
</x-guest-layout>
