@extends('pulse::layout', ['title' => 'VoraPulse SDK'])

@section('content')
    @if ($error)
        <section class="pulse-alert">
            <strong>Falha ao consultar o Pulse central.</strong>
            <span>{{ $error }}</span>
        </section>
    @endif

    <section class="pulse-grid pulse-grid-2">
        <article class="pulse-card">
            <h2>Saude da API</h2>
            <pre>{{ json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </article>

        <article class="pulse-card">
            <h2>Estatisticas</h2>
            <pre>{{ json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </article>
    </section>

    <section class="pulse-grid">
        @foreach ($sections as $section)
            <a class="pulse-card pulse-link-card" href="{{ route('pulse.section', $section['key']) }}">
                <span>{{ $section['label'] }}</span>
                <code>GET /api/v2/{{ $section['endpoint'] }}</code>
            </a>
        @endforeach
    </section>
@endsection
