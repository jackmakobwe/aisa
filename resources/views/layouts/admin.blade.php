<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title') · Aisa dashboard</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ivory" x-data="menu">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2">Skip to content</a>

    <div class="lg:flex">
        {{-- Sidebar --}}
        <aside class="border-b border-line bg-white lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:shrink-0 lg:overflow-y-auto lg:border-r lg:border-b-0">
            <div class="flex h-16 items-center gap-2 px-5">
                <x-logo class="h-8 w-8" />
                <span class="font-serif text-xl font-semibold">Aisa</span>
                <span class="badge-neutral">Dashboard</span>
                <button type="button" class="btn-quiet btn-sm ml-auto lg:hidden" x-on:click="toggle" aria-controls="admin-nav" x-bind:aria-expanded="open" aria-label="Menu">
                    <i data-lucide="menu" class="h-5 w-5"></i>
                </button>
            </div>

            @php
                $nav = [
                    ['admin.dashboard', 'layout-dashboard', 'Overview', null, 'admin.dashboard'],
                    ['admin.safety.index', 'shield-alert', 'Safety panel', $adminCounts['flags'] ?? 0, 'admin.safety.*'],
                    ['admin.conversations.index', 'inbox', 'Conversations', $adminCounts['awaiting'] ?? 0, 'admin.conversations.*'],
                    ['admin.deletions.index', 'trash-2', 'Deletion requests', $adminCounts['deletions'] ?? 0, 'admin.deletions.*'],
                    ['admin.contacts.index', 'mail-warning', 'Contact & reports', $adminCounts['contacts'] ?? 0, 'admin.contacts.*'],
                    ['admin.saved-responses.index', 'bookmark-check', 'Saved responses', null, 'admin.saved-responses.*'],
                    ['admin.analytics', 'chart-column', 'Analytics', null, 'admin.analytics'],
                ];
                $adminNav = [
                    ['admin.resources.index', 'life-buoy', 'Crisis contacts', null, 'admin.resources.*'],
                    ['admin.settings', 'settings', 'Settings & launch', null, 'admin.settings*'],
                    ['admin.users.index', 'users', 'Staff accounts', null, 'admin.users.*'],
                    ['admin.audit', 'file-text', 'Audit log', null, 'admin.audit'],
                ];
            @endphp

            <nav id="admin-nav" class="hidden px-3 pb-6 lg:block" x-bind:class="navClass" aria-label="Dashboard">
                <ul class="space-y-0.5">
                    @foreach ($nav as [$route, $icon, $label, $count, $pattern])
                        <li>
                            <a href="{{ route($route) }}" @class([
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-sm no-underline',
                                'bg-sage-light font-semibold text-forest-dark' => request()->routeIs($pattern),
                                'text-ink-soft hover:bg-sand hover:text-ink' => ! request()->routeIs($pattern),
                            ]) @if(request()->routeIs($pattern)) aria-current="page" @endif>
                                <i data-lucide="{{ $icon }}" class="h-4 w-4"></i>
                                <span class="flex-1">{{ $label }}</span>
                                @if ($count)
                                    <span @class(['rounded-full px-2 py-0.5 text-xs font-bold', 'bg-clay text-white' => $route === 'admin.safety.index', 'bg-sand text-ink' => $route !== 'admin.safety.index'])>{{ $count }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>

                @can('admin')
                    <p class="mt-6 px-3 text-xs font-semibold tracking-wide text-ink-muted uppercase">Administration</p>
                    <ul class="mt-2 space-y-0.5">
                        @foreach ($adminNav as [$route, $icon, $label, $count, $pattern])
                            <li>
                                <a href="{{ route($route) }}" @class([
                                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm no-underline',
                                    'bg-sage-light font-semibold text-forest-dark' => request()->routeIs($pattern),
                                    'text-ink-soft hover:bg-sand hover:text-ink' => ! request()->routeIs($pattern),
                                ])>
                                    <i data-lucide="{{ $icon }}" class="h-4 w-4"></i>{{ $label }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endcan

                <div class="mt-6 border-t border-line pt-4">
                    <a href="{{ route('admin.account') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-ink-soft no-underline hover:bg-sand">
                        <i data-lucide="user-round" class="h-4 w-4"></i>
                        <span class="truncate">{{ auth()->user()->name }}</span>
                        <span class="badge-neutral ml-auto">{{ auth()->user()->role }}</span>
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-ink-soft hover:bg-sand">
                            <i data-lucide="log-out" class="h-4 w-4"></i>Sign out
                        </button>
                    </form>
                </div>
            </nav>
        </aside>

        <main id="main" class="min-w-0 flex-1" tabindex="-1">
            @if (config('aisa.ai.provider') === 'mock')
                <div class="border-b border-amber/30 bg-amber-light px-6 py-2 text-sm" role="note">
                    <strong>Development:</strong> AI replies, summaries and drafts are simulated by the mock provider. No real model is being called.
                </div>
            @endif

            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-10">
                @if (session('status'))
                    <div class="alert-success mb-6" role="status"><i data-lucide="check" class="mt-0.5 h-5 w-5 shrink-0 text-forest"></i><p>{{ session('status') }}</p></div>
                @endif
                @if (session('error'))
                    <div class="alert-danger mb-6" role="alert"><i data-lucide="alert-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-clay"></i><p>{{ session('error') }}</p></div>
                @endif
                @if ($errors->any())
                    <div class="alert-danger mb-6" role="alert">
                        <i data-lucide="alert-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-clay"></i>
                        <ul class="list-disc pl-4">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
