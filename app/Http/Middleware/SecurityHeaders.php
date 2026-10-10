<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $nonce = Vite::cspNonce();

        // Allow the Vite dev server only while developing locally.
        $dev = app()->isLocal() && is_file(public_path('hot')) ? ' '.trim((string) file_get_contents(public_path('hot'))) : '';
        $devWs = $dev ? ' '.str_replace(['http://', 'https://'], ['ws://', 'wss://'], trim($dev)) : '';

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'".$dev,
            "style-src 'self' 'unsafe-inline'".$dev,
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'".$dev.$devWs,
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        // No referrer: Quick exit and outbound links must not reveal this site.
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Pages can contain private conversation text: never cache them, so
        // the back button cannot redisplay a conversation after Quick exit.
        $type = (string) $response->headers->get('Content-Type');
        if (str_contains($type, 'html') || str_contains($type, 'json') || $type === '') {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
