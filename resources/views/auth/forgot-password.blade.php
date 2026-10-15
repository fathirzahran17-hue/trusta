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

        <div class="w-full sm:max-w-md mt-6 px-8 py-10 bg-[#1E293B] shadow-2xl overflow-hidden sm:rounded-2xl border border-gray-800">
            <h2 class="text-xl font-poppins font-semibold text-white mb-2 text-center">Reset Password</h2>
            
            <div class="mb-6 text-sm text-gray-400 text-center">
                {{ __('Lupa password Anda? Tidak masalah. Masukkan alamat email Anda di bawah ini dan kami akan mengirimkan tautan untuk membuat password baru.') }}
            </div>

            <!-- Session Status -->
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <!-- Email Address -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-300">Email</label>
                    <input id="email" class="block mt-1 w-full bg-gray-800 border-gray-700 text-white focus:border-[#14B8A6] focus:ring-[#14B8A6] rounded-md shadow-sm" type="email" name="email" :value="old('email')" required autofocus />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between mt-8">
                    <a href="{{ route('login') }}" class="text-sm text-gray-400 hover:text-white transition-colors">
                        &larr; Kembali ke Login
                    </a>
                    
                    <button type="submit" class="inline-flex justify-center items-center px-4 py-3 bg-[#14B8A6] border border-transparent rounded-lg font-semibold text-xs text-[#0F172A] uppercase tracking-widest hover:bg-teal-400 focus:bg-teal-400 active:bg-teal-500 focus:outline-none focus:ring-2 focus:ring-[#14B8A6] focus:ring-offset-2 focus:ring-offset-gray-800 transition ease-in-out duration-150 shadow-lg shadow-teal-900/50">
                        {{ __('Kirim Link Reset') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
