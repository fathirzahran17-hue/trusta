<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Company;
use Illuminate\Http\Request;

/** Pengumuman super admin ke klien (muncul di lonceng notifikasi klien). */
class AnnouncementController extends Controller
{
    public function index()
    {
        $items = Announcement::with('company')->orderByDesc('id')->paginate(20);
        $companies = Company::orderBy('name')->get(['id', 'name']);

        return view('superadmin.announcements.index', compact('items', 'companies'));
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'title' => 'required|string|max:150',
            'body' => 'nullable|string|max:1000',
            'level' => 'required|in:info,warning,success',
            'url' => 'nullable|url|max:255',
            'company_id' => 'nullable|integer|exists:companies,id',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);
        $v['created_by'] = auth()->id();
        Announcement::create($v);
        \App\Services\NotificationService::bump(); // notifikasi semua pengguna diperbarui

        return back()->with('success', 'Pengumuman dikirim.');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        \App\Services\NotificationService::bump();

        return back()->with('success', 'Pengumuman dihapus.');
    }
}
