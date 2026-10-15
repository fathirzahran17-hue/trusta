<x-app-layout>
    @section('title', 'Edit Kartu Job')
    @section('subtitle', 'Perbarui data proyek atau job')

    <div class="mb-6 flex items-center justify-between">
        <a href="{{ route('client.project-jobs.index') }}" class="text-gray-400 hover:text-white transition-colors flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali
        </a>
    </div>

    <div class="bg-gray-800 rounded-xl border border-gray-700 shadow-xl overflow-hidden max-w-3xl mx-auto">
        <div class="p-6">
                    <form action="{{ route('client.project-jobs.update', $projectJob) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">Kode Job</label>
                            <input type="text" name="code" class="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 focus:border-trusta-teal focus:ring focus:ring-trusta-teal focus:ring-opacity-50" value="{{ old('code', $projectJob->code) }}" placeholder="Opsional (Misal: JOB-01)">
                            @error('code')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">Nama Job <span class="text-red-500">*</span></label>
                            <input type="text" name="name" class="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 focus:border-trusta-teal focus:ring focus:ring-trusta-teal focus:ring-opacity-50" value="{{ old('name', $projectJob->name) }}" required placeholder="Misal: Proyek Gedung A">
                            @error('name')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">Keterangan</label>
                            <textarea name="description" rows="3" class="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 focus:border-trusta-teal focus:ring focus:ring-trusta-teal focus:ring-opacity-50" placeholder="Opsional">{{ old('description', $projectJob->description) }}</textarea>
                            @error('description')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-trusta-teal shadow-sm focus:border-trusta-teal focus:ring focus:ring-trusta-teal focus:ring-opacity-50" {{ old('is_active', $projectJob->is_active) ? 'checked' : '' }}>
                                <span class="ml-2 text-sm">Status Aktif</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end">
                            <a href="{{ route('client.project-jobs.index') }}" class="mr-4 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100">Batal</a>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-trusta-teal border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-teal-600 active:bg-teal-700 focus:outline-none focus:border-teal-700 focus:ring ring-teal-300 disabled:opacity-25 transition ease-in-out duration-150">
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
        </div>
    </div>
</x-app-layout>
