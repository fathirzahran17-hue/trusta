<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ApplyLocale;
use App\Support\UiTranslator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate(['locale' => ['required', Rule::in(ApplyLocale::SUPPORTED)]]);
        if ($user = $request->user()) {
            $user->forceFill(['locale' => $data['locale']])->save();
        }

        return back()->withCookie(cookie(ApplyLocale::COOKIE, $data['locale'], 60 * 24 * 365));
    }

    /** Kamus untuk teks yang muncul lewat JavaScript. Boleh disimpan browser karena alamatnya berubah saat kamusnya berubah. */
    public function dictionary()
    {
        return response()->json(UiTranslator::dictionary(), 200, [], JSON_UNESCAPED_UNICODE)->header('Cache-Control', 'public, max-age=604800');
    }
}
