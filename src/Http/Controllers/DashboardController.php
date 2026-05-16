<?php

namespace Sabbajohn\PulseLaravel\Http\Controllers;

use Illuminate\Routing\Controller;
use Sabbajohn\PulsePhp\Exceptions\PulseException;
use Sabbajohn\PulsePhp\PulseClient;

class DashboardController extends Controller
{
    public function __invoke(PulseClient $pulse)
    {
        $health = null;
        $stats = null;
        $error = null;

        try {
            $health = $pulse->get('health');
        } catch (PulseException $exception) {
            $error = $exception->getMessage();
        }

        try {
            $stats = $pulse->emails()->stats();
        } catch (PulseException) {
            $stats = null;
        }

        return view('pulse::dashboard', [
            'health' => $health,
            'stats' => $stats,
            'error' => $error,
            'sections' => $this->sections(),
        ]);
    }

    private function sections(): array
    {
        return [
            ['key' => 'composer', 'label' => 'Composer', 'endpoint' => 'composer/meta'],
            ['key' => 'templates', 'label' => 'Templates', 'endpoint' => 'templates'],
            ['key' => 'campaigns', 'label' => 'Campanhas', 'endpoint' => 'campaigns'],
            ['key' => 'audiences', 'label' => 'Audiencias', 'endpoint' => 'audiences'],
            ['key' => 'automations', 'label' => 'Automacoes', 'endpoint' => 'automations'],
            ['key' => 'calendar', 'label' => 'Calendario', 'endpoint' => 'calendar/items'],
            ['key' => 'whatsapp', 'label' => 'WhatsApp', 'endpoint' => 'whatsapp/config'],
        ];
    }
}
