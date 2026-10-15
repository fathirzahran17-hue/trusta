<x-app-layout>
    @section('title', 'Profil Akun')
    @section('subtitle', 'Kelola informasi pribadi, keamanan akun, dan preferensi Anda')

    <div class="mb-6">
        <a href="{{ route('dashboard') }}" class="text-trusta-teal hover:text-white flex items-center text-sm transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Dashboard
        </a>
    </div>

    <div class="space-y-6">
        <div class="bg-[#1E293B] border border-gray-800 shadow-2xl rounded-2xl overflow-hidden p-6 md:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="bg-[#1E293B] border border-gray-800 shadow-2xl rounded-2xl overflow-hidden p-6 md:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="bg-red-900/10 border border-red-900/30 shadow-2xl rounded-2xl overflow-hidden p-6 md:p-8">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
