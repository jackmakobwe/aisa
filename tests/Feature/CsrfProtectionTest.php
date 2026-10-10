<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

/**
 * Laravel skips CSRF checks while running tests, so this exercises the
 * middleware directly with that shortcut turned off.
 */
class CsrfProtectionTest extends TestCase
{
    public function test_post_without_a_token_is_rejected(): void
    {
        $middleware = new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };

        $request = Request::create('/conversation/messages', 'POST', ['message' => 'hi']);
        $request->setLaravelSession($this->app['session.store']);

        $this->expectException(TokenMismatchException::class);
        $middleware->handle($request, fn () => response('ok'));
    }
}
