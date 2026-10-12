<?php

namespace App\Http\Controllers;

use App\Services\TicketAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Called in the background (navigator.sendBeacon) when someone presses
 * Quick exit. The browser has already navigated away; this ends the
 * server-side session so going "back" does not reopen the conversation.
 *
 * It cannot erase browser history, server logs or network records, and
 * the site says so on the How it works and Privacy pages.
 */
class QuickExitController extends Controller
{
    public function __invoke(Request $request, TicketAccess $access): Response
    {
        $access->revoke();
        Auth::guard('member')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $response = response()->noContent();
        if (config('aisa.quick_exit.forget_device')) {
            $response->withCookie($access->forgetCookie());
        }

        return $response;
    }
}
