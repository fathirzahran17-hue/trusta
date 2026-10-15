<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-red-400">
            Hapus Akun
        </h2>

        <p class="mt-1 text-sm text-gray-300">
            Setelah akun Anda dihapus, semua sumber daya dan data di dalamnya akan dihapus secara permanen. Pastikan Anda sudah mengunduh data penting sebelum melanjutkan.
        </p>
    </header>

    <button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        class="px-4 py-2 bg-red-900/50 hover:bg-red-800 text-red-200 font-bold rounded-lg text-sm transition-colors border border-red-800"
    >Hapus Akun Permanen</button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6 bg-gray-900 border border-gray-700 rounded-2xl">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-white">
                Apakah Anda yakin ingin menghapus akun ini?
            </h2>

            <p class="mt-1 text-sm text-gray-400">
                Setelah akun Anda dihapus, semua sumber daya dan data akan dihapus secara permanen. Masukkan password Anda untuk mengonfirmasi bahwa Anda ingin menghapus akun ini secara permanen.
            </p>

            <div class="mt-6">
                <label for="password" class="sr-only">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4 bg-gray-800 border-gray-700 text-white focus:border-red-500 focus:ring-red-500 rounded-lg shadow-sm"
                    placeholder="Password Anda"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2 text-red-400" />
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" x-on:click="$dispatch('close')" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg text-sm transition-colors border border-gray-700">
                    Batal
                </button>

                <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-lg text-sm transition-colors shadow-lg shadow-red-900/50">
                    Hapus Akun
                </button>
            </div>
        </form>
    </x-modal>
</section>
