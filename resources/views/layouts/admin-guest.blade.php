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
<body class="flex min-h-screen items-center justify-center bg-sand/60 px-4 py-12">
    <main class="w-full max-w-md">
        <div class="mb-6 flex items-center justify-center gap-2">
            <x-logo class="h-10 w-10" />
            <span class="font-serif text-2xl font-semibold">Aisa</span>
            <span class="badge-neutral">Dashboard</span>
        </div>
        <div class="card">
            @if (session('status'))
                <div class="alert-success mb-5" role="status"><p>{{ session('status') }}</p></div>
            @endif
            @yield('content')
        </div>
    </main>
</body>
</html>
