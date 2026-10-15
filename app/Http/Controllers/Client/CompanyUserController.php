<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class CompanyUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::where('company_id', auth()->user()->company_id)
            ->where('id', '!=', auth()->id()) // Don't show self in this management list
            ->orderBy('name')
            ->get();
            
        return view('client.users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('client.users.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        User::create([
            'company_id' => auth()->user()->company_id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'client_staff',
            'is_active' => true,
        ]);

        return redirect()->route('client.users.index')->with('success', 'Akun staf berhasil dibuat.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        if ($user->company_id !== auth()->user()->company_id) abort(403);
        
        return view('client.users.edit', compact('user'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        if ($user->company_id !== auth()->user()->company_id) abort(403);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'is_active' => ['boolean']
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->is_active = $request->has('is_active');
        
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        
        $user->save();

        return redirect()->route('client.users.index')->with('success', 'Akun staf berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        if ($user->company_id !== auth()->user()->company_id) abort(403);
        if ($user->role === 'client_admin') {
            return back()->with('error', 'Tidak dapat menghapus akun admin.');
        }

        $user->delete();
        
        return redirect()->route('client.users.index')->with('success', 'Akun staf berhasil dihapus.');
    }
}
