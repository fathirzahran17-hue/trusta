<x-guest-layout>
    <!-- Custom Styles for Auth -->
    <style>
        body {
            background-color: #0F172A; /* trusta-navy */
            color: #F8FAFC;
        }
    </style>

    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-[#0F172A]">
        
        <div class="mb-8 text-center">
            <div class="text-4xl font-poppins font-bold tracking-widest text-white flex items-baseline justify-center">
                TRUST<span class="text-[#C9A84C]">A</span>
            </div>
            <div class="h-0.5 w-full bg-[#14B8A6] mt-2 mb-2"></div>
            <span class="text-xs uppercase tracking-[0.2em] text-gray-400">Trusted Accounting Solution</span>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <div class="w-full sm:max-w-md mt-6 px-8 py-10 bg-[#1E293B] shadow-2xl overflow-hidden sm:rounded-2xl border border-gray-800">
            <h2 class="text-xl font-poppins font-semibold text-white mb-6 text-center">Login to your account</h2>
            
            <form method="POST" action="{{ route('login') }}">
                @csrf

                <!-- Email Address -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-300">Email</label>
                    <input id="email" class="block mt-1 w-full bg-gray-800 border-gray-700 text-white focus:border-[#14B8A6] focus:ring-[#14B8A6] rounded-md shadow-sm" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <!-- Password -->
                <div class="mt-5" x-data="{ show: false }">
                    <label for="password" class="block text-sm font-medium text-gray-300">Password</label>
                    <div class="relative mt-1">
                        <input id="password" class="block w-full bg-gray-800 border-gray-700 text-white focus:border-[#14B8A6] focus:ring-[#14B8A6] rounded-md shadow-sm pr-10"
                                        :type="show ? 'text' : 'password'"
                                        name="password"
                                        required autocomplete="current-password" />
                        <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-white">
                            <svg x-show="!show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg x-show="show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0a10.05 10.05 0 015.188-1.562c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <!-- Remember Me -->
                <div class="block mt-4 flex items-center justify-between">
                    <label for="remember_me" class="inline-flex items-center">
                        <input id="remember_me" type="checkbox" class="rounded border-gray-700 bg-gray-800 text-[#14B8A6] shadow-sm focus:ring-[#14B8A6]" name="remember">
                        <span class="ms-2 text-sm text-gray-400">Remember me</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a class="text-sm text-[#14B8A6] hover:text-white rounded-md focus:outline-none transition-colors" href="{{ route('password.request') }}">
                            Forgot password?
                        </a>
                    @endif
                </div>

                <div class="flex items-center justify-end mt-8">
                    <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-3 bg-[#14B8A6] border border-transparent rounded-lg font-semibold text-xs text-[#0F172A] uppercase tracking-widest hover:bg-teal-400 focus:bg-teal-400 active:bg-teal-500 focus:outline-none focus:ring-2 focus:ring-[#14B8A6] focus:ring-offset-2 focus:ring-offset-gray-800 transition ease-in-out duration-150 shadow-lg shadow-teal-900/50">
                        Sign In
                    </button>
                </div>

                <div class="mt-6 flex items-center justify-center">
                    <span class="h-px bg-gray-700 w-full"></span>
                    <span class="px-3 text-xs text-gray-500 uppercase">Or</span>
                    <span class="h-px bg-gray-700 w-full"></span>
                </div>

                <div class="mt-6">
                    <a href="{{ route('google.login') }}" class="w-full inline-flex justify-center items-center px-4 py-3 bg-white border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm">
                        <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                            <path fill="none" d="M1 1h22v22H1z" />
                        </svg>
                        Sign in with Google
                    </a>
                </div>
                
                @if (Route::has('register'))
                    <div class="mt-6 text-center text-sm text-gray-400">
                        Don't have an account? 
                        <a href="{{ route('register') }}" class="font-medium text-[#14B8A6] hover:text-white transition-colors">
                            Register Here
                        </a>
                    </div>
                @endif
            </form>
        </div>
    </div>
</x-guest-layout>
