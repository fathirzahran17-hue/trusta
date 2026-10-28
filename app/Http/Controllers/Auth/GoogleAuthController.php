<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Exception;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::where('google_id', $googleUser->id)->orWhere('email', $googleUser->email)->first();

            if ($user) {
                if (! $user->is_active || ($user->company && ! $user->company->is_active)) {
                    return redirect('/login')->with('error', 'Akun Anda dinonaktifkan. Hubungi administrator.');
                }
                // Update google_id and avatar if missing
                if (!$user->google_id) {
                    $user->update([
                        'google_id' => $googleUser->id,
                        'avatar' => $googleUser->avatar
                    ]);
                }
                
                Auth::login($user);
                request()->session()->regenerate();

                if ($user->isSuperAdmin()) {
                    return redirect()->route('superadmin.dashboard');
                } else {
                    return redirect()->route('client.dashboard');
                }
            } else {
                // Jika belum ada akun dengan email ini, kita tolak atau kita buatkan baru?
                // Lebih baik buatkan baru jika belum ada, asumsikan default role client_admin.
                // Tapi ini tergantung business logic. Kita asumsikan buat baru.
                
                // Buatkan perusahaan otomatis untuk pendaftar baru
                $company = Company::create([
                    'name' => 'Perusahaan ' . $googleUser->name,
                    'email' => $googleUser->email,
                    'is_active' => true,
                    'is_premium' => false,
                    'business_type' => \App\Support\BusinessType::DEFAULT,
                ]);
                // Bagan akun standar (sebelumnya pendaftar lewat Google tidak mendapat akun sama sekali); bidang usaha bisa diganti di Preferensi
                \App\Services\CoaSeeder::seed($company->id, $company->business_type);
        \App\Services\SubscriptionService::startTrialIfEnabled($company); // trial otomatis bila diaktifkan super admin

                $newUser = User::create([
                    'company_id' => $company->id,
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar,
                    'password' => null, // Password null karna login pakai Google
                    'role' => 'client_admin', // Default role
                    'is_active' => true,
                ]);

                Auth::login($newUser);
                request()->session()->regenerate();
                
                return redirect()->route('client.dashboard');
            }

        } catch (Exception $e) {
            return redirect('/login')->with('error', 'Gagal login menggunakan Google. Silakan coba lagi. Pastikan konfigurasi .env sudah benar.');
        }
    }
}
