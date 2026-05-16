# sabbajohn/pulse-laravel

Pacote Laravel para integrar projetos ao VoraPulse via Composer.

## Instalacao

```bash
composer require sabbajohn/pulse-laravel
php artisan pulse:install
```

## Configuracao

```env
PULSE_BASE_URL=https://pulse.seu-dominio.com
PULSE_API_TOKEN=seu-token
PULSE_ROUTE_PREFIX=pulse
PULSE_MIDDLEWARE=web,auth
```

## Uso

```php
use Sabbajohn\PulseLaravel\Facades\Pulse;

Pulse::emails()->sendAsync([
    'to' => [['email' => 'cliente@example.com']],
    'subject' => 'Processando',
    'html' => '<p>Recebemos sua solicitacao.</p>',
]);
```

A UI proxy local fica em `/{PULSE_ROUTE_PREFIX}`.
