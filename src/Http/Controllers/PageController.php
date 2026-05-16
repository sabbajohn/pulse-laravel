<?php

namespace Sabbajohn\PulseLaravel\Http\Controllers;

use Illuminate\Routing\Controller;

class PageController extends Controller
{
    public function __invoke(?string $section = null)
    {
        $sections = [
            'composer' => ['label' => 'Composer', 'endpoint' => 'composer/meta', 'method' => 'GET'],
            'templates' => ['label' => 'Templates', 'endpoint' => 'templates', 'method' => 'GET'],
            'campaigns' => ['label' => 'Campanhas', 'endpoint' => 'campaigns', 'method' => 'GET'],
            'audiences' => ['label' => 'Audiencias', 'endpoint' => 'audiences', 'method' => 'GET'],
            'automations' => ['label' => 'Automacoes', 'endpoint' => 'automations', 'method' => 'GET'],
            'calendar' => ['label' => 'Calendario', 'endpoint' => 'calendar/items', 'method' => 'GET'],
            'whatsapp' => ['label' => 'WhatsApp', 'endpoint' => 'whatsapp/config', 'method' => 'GET'],
        ];

        abort_unless(isset($sections[$section]), 404);

        return view('pulse::section', [
            'section' => $section,
            'meta' => $sections[$section],
            'sections' => $sections,
        ]);
    }
}
