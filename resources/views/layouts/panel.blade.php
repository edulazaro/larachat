<!DOCTYPE html>
<html lang="{{ config('larachat.panel.locale', 'es') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('larachat::panel.admin') }} · {{ config('larachat.panel.name') }}</title>
    @if (config('larachat.panel.favicon'))
        <link rel="icon" href="{{ config('larachat.panel.favicon') }}">
    @endif
    <link rel="stylesheet" href="{{ \EduLazaro\Larachat\Http\Controllers\AssetController::url('panel.css') }}">
    @livewireStyles
</head>
<body class="lc">
    @if (\EduLazaro\Larachat\Larachat::isAdmin(auth()->user()))
    <header class="lc-bar">
        <a href="{{ route('larachat.panel') }}" class="lc-brand">{{ config('larachat.panel.name') }} <span>{{ __('larachat::panel.admin') }}</span></a>
        <nav class="lc-nav">
            <a href="{{ route('larachat.panel') }}" @class(['is-active' => request()->routeIs('larachat.panel')])>{{ __('larachat::panel.conversations') }}</a>
            <a href="{{ route('larachat.profile') }}" @class(['is-active' => request()->routeIs('larachat.profile')])>{{ __('larachat::panel.profile') }}</a>
            <form method="POST" action="{{ route('larachat.logout') }}">
                @csrf
                <button type="submit">{{ __('larachat::panel.logout') }}</button>
            </form>
        </nav>
    </header>
    @endif

    <main class="lc-main">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
