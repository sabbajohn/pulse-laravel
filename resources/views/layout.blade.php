<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'VoraPulse' }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/pulse/pulse.css') }}">
</head>
<body>
    <main class="pulse-shell">
        <header class="pulse-header">
            <div>
                <a class="pulse-brand" href="{{ route('pulse.dashboard') }}">VoraPulse</a>
                <p>SDK local conectado ao Pulse central</p>
            </div>
            <nav>
                <a href="{{ route('pulse.dashboard') }}">Dashboard</a>
                <a href="{{ route('pulse.section', 'composer') }}">Composer</a>
                <a href="{{ route('pulse.section', 'automations') }}">Automacoes</a>
            </nav>
        </header>

        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
