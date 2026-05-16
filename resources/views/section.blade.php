@extends('pulse::layout', ['title' => 'VoraPulse - '.$meta['label']])

@section('content')
    <section class="pulse-card">
        <div class="pulse-section-heading">
            <div>
                <h1>{{ $meta['label'] }}</h1>
                <p>Console local para operar este dominio via proxy seguro do SDK.</p>
            </div>
            <code>{{ $meta['method'] }} /api/v2/{{ $meta['endpoint'] }}</code>
        </div>

        <div class="pulse-console">
            <label>
                Metodo
                <select id="pulse-method">
                    @foreach (['GET', 'POST', 'PATCH', 'DELETE'] as $method)
                        <option @selected($method === $meta['method'])>{{ $method }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                Endpoint
                <input id="pulse-endpoint" value="{{ $meta['endpoint'] }}">
            </label>

            <label>
                JSON
                <textarea id="pulse-body" rows="12" spellcheck="false">{
}</textarea>
            </label>

            <button id="pulse-send" type="button">Enviar</button>
        </div>
    </section>

    <section class="pulse-card">
        <h2>Resposta</h2>
        <pre id="pulse-response">Aguardando requisicao.</pre>
    </section>
@endsection

@push('scripts')
    <script>
        const prefix = @json(url(config('pulse.route_prefix', 'pulse').'/api'));
        const methodInput = document.getElementById('pulse-method');
        const endpointInput = document.getElementById('pulse-endpoint');
        const bodyInput = document.getElementById('pulse-body');
        const responseOutput = document.getElementById('pulse-response');

        async function sendPulseRequest() {
            const method = methodInput.value;
            const endpoint = endpointInput.value.replace(/^\/+/, '');
            const options = {
                method,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            };

            if (!['GET', 'HEAD'].includes(method)) {
                options.body = bodyInput.value.trim() || '{}';
            }

            responseOutput.textContent = 'Carregando...';

            try {
                const response = await fetch(`${prefix}/${endpoint}`, options);
                const json = await response.json();
                responseOutput.textContent = JSON.stringify(json, null, 2);
            } catch (error) {
                responseOutput.textContent = error.message;
            }
        }

        document.getElementById('pulse-send').addEventListener('click', sendPulseRequest);
        sendPulseRequest();
    </script>
@endpush
