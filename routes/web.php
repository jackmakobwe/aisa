<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\GoogleController;
use App\Http\Controllers\Account\MemberAuthController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ChooseHelpController;
use App\Http\Controllers\CodeController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\QuickExitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
*/

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/how-it-works', [PageController::class, 'howItWorks'])->name('how');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/get-help', [PageController::class, 'getHelp'])->name('get-help');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/not-open', [PageController::class, 'notOpen'])->name('not-open');
Route::get('/under-18', [PageController::class, 'underAge'])->name('under-18');

Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/{slug}', [ArticleController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('articles.show');

Route::get('/language/{locale}', LocaleController::class)->whereIn('locale', ['en', 'sw'])->name('locale');
Route::post('/quick-exit', QuickExitController::class)->name('quick-exit');

Route::get('/contact', [ContactController::class, 'show'])->name('contact.show');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');

// Starting a conversation: message, then consent, then the conversation.
Route::middleware('launch-ready')->group(function () {
    Route::post('/start', [ConsentController::class, 'start'])->name('start');
    Route::get('/consent', [ConsentController::class, 'show'])->name('consent.show');
    Route::post('/consent', [ConsentController::class, 'store'])->middleware('throttle:start')->name('consent.store');
    Route::post('/consent/decline', [ConsentController::class, 'decline'])->name('consent.decline');
});

// Optional accounts (members). Separate from the staff dashboard.
Route::prefix('account')->name('account.')->group(function () {
    Route::get('/login', [MemberAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [MemberAuthController::class, 'login'])->middleware('throttle:member-auth')->name('login.store');
    Route::get('/register', [MemberAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [MemberAuthController::class, 'register'])->middleware('throttle:member-auth')->name('register.store');
    Route::get('/check-email', [MemberAuthController::class, 'checkEmail'])->name('check-email');
    Route::post('/logout', [MemberAuthController::class, 'logout'])->name('logout');

    Route::get('/verify', [MemberAuthController::class, 'verifyNotice'])->name('verify.notice');
    Route::post('/verify/resend', [MemberAuthController::class, 'resendVerification'])->middleware('throttle:member-auth')->name('verify.resend');
    Route::get('/verify/{id}/{hash}', [MemberAuthController::class, 'verify'])->whereNumber('id')->middleware(['signed', 'throttle:member-auth'])->name('verify');

    Route::get('/forgot-password', [MemberAuthController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [MemberAuthController::class, 'sendResetLink'])->middleware('throttle:member-auth')->name('password.email');
    Route::get('/reset-password/{token}', [MemberAuthController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [MemberAuthController::class, 'reset'])->middleware('throttle:member-auth')->name('password.update');

    Route::get('/google', [GoogleController::class, 'redirect'])->name('google');
    Route::get('/google/callback', [GoogleController::class, 'callback'])->middleware('throttle:member-auth')->name('google.callback');

    Route::middleware('member')->group(function () {
        Route::get('/', [AccountController::class, 'index'])->name('index');
        Route::post('/conversations/{reference}', [AccountController::class, 'open'])->where('reference', '[A-Za-z0-9-]+')->name('open');
        Route::post('/save-current', [AccountController::class, 'saveCurrent'])->name('save-current');
        Route::post('/name', [AccountController::class, 'updateName'])->name('name');
        Route::post('/delete', [AccountController::class, 'destroy'])->name('destroy');
    });
});

// Returning with a code.
Route::get('/check', [CodeController::class, 'show'])->name('check.show');
Route::post('/check', [CodeController::class, 'store'])->middleware('throttle:code-lookup')->name('check.store');
Route::post('/check/remembered', [CodeController::class, 'remembered'])->middleware('throttle:code-lookup')->name('check.remembered');
Route::post('/forget-device', [CodeController::class, 'forgetDevice'])->name('forget-device');

// Everything below needs access to a ticket granted in this session.
Route::middleware('ticket')->group(function () {
    Route::get('/conversation', [ConversationController::class, 'show'])->name('conversation.show');
    Route::post('/conversation/messages', [ConversationController::class, 'store'])->middleware('throttle:messages')->name('conversation.messages');
    Route::post('/conversation/retry', [ConversationController::class, 'retry'])->middleware('throttle:messages')->name('conversation.retry');
    Route::post('/conversation/leave', [ConversationController::class, 'leave'])->name('conversation.leave');
    Route::post('/conversation/rating', [ConversationController::class, 'rate'])->name('conversation.rating');
    Route::post('/conversation/delete', [ConversationController::class, 'requestDeletion'])->name('conversation.delete');

    Route::get('/choose-help', [ChooseHelpController::class, 'show'])->name('help.show');
    Route::post('/choose-help', [ChooseHelpController::class, 'store'])->name('help.store');

    Route::get('/your-code', [CodeController::class, 'issued'])->name('code.issued');
    Route::post('/your-code', [CodeController::class, 'issue'])->name('code.issue');
    Route::post('/your-code/saved', [CodeController::class, 'saved'])->name('code.saved');
});

/*
|--------------------------------------------------------------------------
| Aisa support dashboard
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [Admin\AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [Admin\AuthController::class, 'login'])->name('login.attempt');
        Route::get('forgot-password', [Admin\PasswordResetController::class, 'request'])->name('password.request');
        Route::post('forgot-password', [Admin\PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
        Route::get('reset-password/{token}', [Admin\PasswordResetController::class, 'reset'])->name('password.reset');
        Route::post('reset-password', [Admin\PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
    });

    Route::middleware(['auth', 'admin.session', 'throttle:admin'])->group(function () {
        Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');

        Route::get('two-factor/setup', [Admin\TwoFactorController::class, 'setup'])->name('two-factor.setup');
        Route::post('two-factor/setup', [Admin\TwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('two-factor.setup.confirm');
        Route::get('two-factor/challenge', [Admin\TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
        Route::post('two-factor/challenge', [Admin\TwoFactorController::class, 'verify'])->middleware('throttle:5,1')->name('two-factor.challenge.verify');

        Route::middleware('can:respond')->group(function () {
            Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

            Route::get('conversations', [Admin\ConversationController::class, 'index'])->name('conversations.index');
            Route::get('conversations/{ticket}', [Admin\ConversationController::class, 'show'])->name('conversations.show');
            Route::post('conversations/{ticket}/reply', [Admin\ConversationController::class, 'reply'])->name('conversations.reply');
            Route::post('conversations/{ticket}/draft', [Admin\ConversationController::class, 'regenerateDraft'])->name('conversations.draft');
            Route::post('conversations/{ticket}/notes', [Admin\ConversationController::class, 'addNote'])->name('conversations.notes');
            Route::post('conversations/{ticket}/status', [Admin\ConversationController::class, 'updateStatus'])->name('conversations.status');
            Route::post('conversations/{ticket}/classification', [Admin\ConversationController::class, 'classify'])->name('conversations.classify');
            Route::post('conversations/{ticket}/block', [Admin\ConversationController::class, 'block'])->name('conversations.block');
            Route::post('conversations/{ticket}/flag', [Admin\SafetyController::class, 'flag'])->name('conversations.flag');

            Route::get('safety', [Admin\SafetyController::class, 'index'])->name('safety.index');
            Route::post('safety/{flag}/assign', [Admin\SafetyController::class, 'assign'])->name('safety.assign');
            Route::post('safety/{flag}/resolve', [Admin\SafetyController::class, 'resolve'])->name('safety.resolve');

            Route::resource('saved-responses', Admin\SavedResponseController::class)->except(['show']);

            Route::get('deletions', [Admin\DeletionController::class, 'index'])->name('deletions.index');
            Route::post('deletions/{deletion}/complete', [Admin\DeletionController::class, 'complete'])->name('deletions.complete');
            Route::post('deletions/{deletion}/reject', [Admin\DeletionController::class, 'reject'])->name('deletions.reject');

            Route::get('contacts', [Admin\ContactController::class, 'index'])->name('contacts.index');
            Route::post('contacts/{contact}/handled', [Admin\ContactController::class, 'handled'])->name('contacts.handled');

            Route::get('analytics', [Admin\AnalyticsController::class, 'index'])->name('analytics');

            Route::get('account', [Admin\AccountController::class, 'show'])->name('account');
            Route::post('account/password', [Admin\AccountController::class, 'password'])->name('account.password');
            Route::post('account/recovery-codes', [Admin\AccountController::class, 'recoveryCodes'])->name('account.recovery-codes');
        });

        Route::middleware('can:admin')->group(function () {
            Route::post('conversations/{ticket}/delete', [Admin\ConversationController::class, 'destroy'])->name('conversations.destroy');
            Route::get('conversations/{ticket}/export', [Admin\ConversationController::class, 'export'])->name('conversations.export');

            Route::resource('resources', Admin\CrisisResourceController::class)->except(['show']);
            Route::post('resources/{resource}/verify', [Admin\CrisisResourceController::class, 'verify'])->name('resources.verify');

            Route::get('settings', [Admin\SettingsController::class, 'index'])->name('settings');
            Route::post('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');

            Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
            Route::post('users', [Admin\UserController::class, 'store'])->name('users.store');
            Route::post('users/{user}/toggle', [Admin\UserController::class, 'toggle'])->name('users.toggle');
            Route::post('users/{user}/reset-two-factor', [Admin\UserController::class, 'resetTwoFactor'])->name('users.reset-two-factor');

            Route::get('audit', [Admin\AuditController::class, 'index'])->name('audit');
        });
    });
});
