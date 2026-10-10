<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $request->session()->put('locale', $locale);

        // Only return to a page on this site.
        $previous = url()->previous();
        $target = str_starts_with($previous, url('/')) ? $previous : route('home');

        return redirect()->to($target)->withCookie(cookie(SetLocale::COOKIE, $locale, 60 * 24 * 365, httpOnly: true, sameSite: 'lax'));
    }
}
