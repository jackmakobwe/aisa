<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="no-referrer">
    <meta name="aisa-quick-exit" content="{{ config('aisa.quick_exit.url') }}">
    <meta name="aisa-quick-exit-endpoint" content="{{ route('quick-exit') }}">
    <meta name="description" content="{{ __('ui.meta_description') }}">
    @stack('meta')
    <title>@hasSection('title')@yield('title') · @endif{{ __('ui.site_name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col" x-data="quickExit">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">{{ __('ui.skip_to_content') }}</a>

    @if (! empty($devNotices))
        <div class="no-print border-b border-amber/30 bg-amber-light text-sm text-ink" role="note">
            <div class="container-page flex flex-wrap items-center gap-x-2 gap-y-1 py-2">
                <i data-lucide="alert-triangle" class="h-4 w-4 shrink-0 text-amber"></i>
                <strong>{{ __('ui.dev_banner_title') }}</strong>
                <span>{{ implode(' · ', $devNotices) }}</span>
            </div>
        </div>
    @endif

    <header class="sticky top-0 z-40 border-b border-line bg-ivory/95 backdrop-blur supports-[backdrop-filter]:bg-ivory/85" x-data="menu">
        <div class="container-page flex h-16 items-center gap-3">
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-ink no-underline" aria-label="{{ __('ui.home_link_label') }}">
                <x-logo class="h-9 w-9" />
                <span class="hidden font-serif text-2xl font-semibold tracking-tight sm:inline">Aisa</span>
            </a>

            <nav class="ml-6 hidden items-center gap-0.5 whitespace-nowrap xl:flex" aria-label="{{ __('ui.main_nav') }}">
                <a href="{{ route('home') }}" class="rounded-lg px-2.5 py-2 text-ink-soft no-underline hover:bg-sand hover:text-ink @if(request()->routeIs('home')) text-ink font-semibold @endif">{{ __('ui.nav_home') }}</a>
                <a href="{{ route('how') }}" class="rounded-lg px-2.5 py-2 text-ink-soft no-underline hover:bg-sand hover:text-ink @if(request()->routeIs('how')) text-ink font-semibold @endif">{{ __('ui.nav_how') }}</a>
                <a href="{{ route('articles.index') }}" class="rounded-lg px-2.5 py-2 text-ink-soft no-underline hover:bg-sand hover:text-ink @if(request()->routeIs('articles.*')) text-ink font-semibold @endif">{{ __('ui.nav_articles') }}</a>
                <a href="{{ route('about') }}" class="rounded-lg px-2.5 py-2 text-ink-soft no-underline hover:bg-sand hover:text-ink @if(request()->routeIs('about')) text-ink font-semibold @endif">{{ __('ui.nav_about') }}</a>
                @guest('member')
                    <a href="{{ route('check.show') }}" class="rounded-lg px-2.5 py-2 text-ink-soft no-underline hover:bg-sand hover:text-ink @if(request()->routeIs('check.*')) text-ink font-semibold @endif">{{ __('ui.nav_check') }}</a>
                @endguest
                @auth('member')
                    <a href="{{ route('account.index') }}" class="ml-1.5 inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-line-strong bg-white px-3 py-2 text-ink no-underline hover:bg-sand @if(request()->routeIs('account.*')) font-semibold @endif"><i data-lucide="user-round" class="h-4 w-4 text-forest"></i>{{ __('account.nav_account') }}</a>
                @else
                    <a href="{{ route('account.login') }}" class="ml-1.5 inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-line-strong bg-white px-3 py-2 text-ink no-underline hover:bg-sand @if(request()->routeIs('account.*')) font-semibold @endif"><i data-lucide="user-round" class="h-4 w-4 text-forest"></i>{{ __('account.nav_sign_in') }}</a>
                @endauth
            </nav>

            <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
                <x-language-toggle class="hidden sm:flex" />

                <a href="{{ route('get-help') }}" class="btn-sm border border-clay/40 bg-clay-light text-clay hover:bg-clay hover:text-white" aria-label="{{ __('ui.get_help_now') }}">
                    <i data-lucide="life-buoy" class="h-4 w-4"></i>
                    {{-- Short label on small phones so both safety controls fit on one line. --}}
                    <span class="whitespace-nowrap sm:hidden">{{ __('ui.get_help_short') }}</span>
                    <span class="hidden whitespace-nowrap sm:inline">{{ __('ui.get_help_now') }}</span>
                </a>

                <button type="button" x-on:click="exit" class="btn-sm bg-ink text-white hover:bg-black" title="{{ __('ui.quick_exit_hint') }}">
                    <i data-lucide="door-open" class="h-4 w-4"></i>
                    <span class="whitespace-nowrap">{{ __('ui.quick_exit') }}</span>
                </button>

                <button type="button" class="btn-quiet btn-sm xl:hidden" x-on:click="toggle" aria-controls="mobile-nav" x-bind:aria-expanded="open" aria-label="{{ __('ui.menu') }}">
                    <i data-lucide="menu" class="h-5 w-5"></i>
                </button>
            </div>
        </div>

        <nav id="mobile-nav" class="border-t border-line bg-ivory xl:hidden" x-show="open" x-cloak aria-label="{{ __('ui.main_nav') }}">
            <div class="container-page flex flex-col gap-1 py-3">
                <a href="{{ route('home') }}" class="rounded-lg px-3 py-2.5 text-ink no-underline hover:bg-sand">{{ __('ui.nav_home') }}</a>
                <a href="{{ route('how') }}" class="rounded-lg px-3 py-2.5 text-ink no-underline hover:bg-sand">{{ __('ui.nav_how') }}</a>
                <a href="{{ route('articles.index') }}" class="rounded-lg px-3 py-2.5 text-ink no-underline hover:bg-sand">{{ __('ui.nav_articles') }}</a>
                <a href="{{ route('about') }}" class="rounded-lg px-3 py-2.5 text-ink no-underline hover:bg-sand">{{ __('ui.nav_about') }}</a>
                <a href="{{ route('check.show') }}" class="rounded-lg px-3 py-2.5 text-ink no-underline hover:bg-sand">{{ __('ui.nav_check') }}</a>
                <a href="{{ route('contact.show') }}" class="rounded-lg px-3 py-2.5 text-ink no-underline hover:bg-sand">{{ __('ui.nav_contact') }}</a>
                @auth('member')
                    <a href="{{ route('account.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2.5 font-semibold text-forest no-underline hover:bg-sand"><i data-lucide="user-round" class="h-4 w-4"></i>{{ __('account.nav_account') }}</a>
                @else
                    <a href="{{ route('account.login') }}" class="flex items-center gap-2 rounded-lg px-3 py-2.5 font-semibold text-forest no-underline hover:bg-sand"><i data-lucide="user-round" class="h-4 w-4"></i>{{ __('account.nav_sign_in') }}</a>
                @endauth
                <x-language-toggle class="mt-2 px-3" />
            </div>
        </nav>
    </header>

    <main id="main" class="flex-1" tabindex="-1">
        @if (session('status'))
            <div class="container-page pt-6">
                <div class="reading alert-success" role="status">
                    <i data-lucide="check" class="mt-0.5 h-5 w-5 shrink-0 text-forest"></i>
                    <p>{{ session('status') }}</p>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="mt-16 border-t border-line bg-sand/60">
        <div class="container-page grid gap-8 py-10 md:grid-cols-3">
            <div>
                <div class="flex items-center gap-2">
                    <x-logo class="h-8 w-8" />
                    <span class="font-serif text-xl font-semibold">Aisa</span>
                </div>
                <p class="mt-3 text-sm text-ink-muted">{{ __('ui.footer_tagline') }}</p>
            </div>
            <nav aria-label="{{ __('ui.footer_nav') }}" class="grid grid-cols-2 gap-2 text-sm">
                <a href="{{ route('how') }}">{{ __('ui.nav_how') }}</a>
                <a href="{{ route('about') }}">{{ __('ui.nav_about') }}</a>
                <a href="{{ route('articles.index') }}">{{ __('ui.nav_articles') }}</a>
                <a href="{{ route('privacy') }}">{{ __('ui.nav_privacy') }}</a>
                <a href="{{ route('terms') }}">{{ __('ui.nav_terms') }}</a>
                <a href="{{ route('contact.show') }}">{{ __('ui.nav_contact') }}</a>
                <a href="{{ route('check.show') }}">{{ __('ui.nav_check') }}</a>
                <a href="{{ route('get-help') }}" class="font-semibold text-clay">{{ __('ui.get_help_now') }}</a>
            </nav>
            <div class="text-sm">
                <x-language-toggle />
                <p class="mt-4 text-ink-muted">{{ __('ui.disclaimer') }}</p>
            </div>
        </div>
        {{-- Room so the floating chat button never covers the last footer line. --}}
        <div class="h-16" aria-hidden="true"></div>
    </footer>

    <x-chat-launcher />
</body>
</html>
