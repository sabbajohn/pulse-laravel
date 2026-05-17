# sabbajohn/pulse-laravel

Pacote Laravel para integrar projetos ao VoraPulse via Composer.

Versao atual: `0.2.2`.

## O que este pacote entrega

- `Pulse` facade para acessar os services do cliente PHP.
- Service provider Laravel com config publicavel.
- UI/proxy local em `/{PULSE_ROUTE_PREFIX}` para operacao assistida.
- Resolucao dinamica de credenciais para apps multi-tenant.

## Compatibilidade

- PHP `^8.2`
- Laravel `9.x`, `10.x`, `11.x` ou `12.x`
- `sabbajohn/pulse-php` `^0.1`

## Instalacao

```bash
composer require sabbajohn/pulse-laravel:^0.2
php artisan pulse:install
```

Se `sabbajohn/pulse-php` ainda nao estiver disponivel no Packagist do projeto consumidor, registre o repositorio VCS do pacote base antes da instalacao:

```bash
composer config repositories.pulse-php vcs https://github.com/sabbajohn/pulse-php.git
composer require sabbajohn/pulse-laravel:^0.2
```

Se o Packagist ainda nao tiver indexado a ultima tag do pacote Laravel, registre tambem o repositorio VCS deste pacote:

```bash
composer config repositories.pulse-laravel vcs https://github.com/sabbajohn/pulse-laravel.git
composer require sabbajohn/pulse-laravel:^0.2
```

Se estiver usando `zsh` e quiser testar qualquer versao disponivel, coloque a constraint entre aspas para o shell nao expandir `*`:

```bash
composer require 'sabbajohn/pulse-laravel:*'
```

Se o Composer indicar conflito com `illuminate/console`, confira a versao do Laravel do projeto consumidor:

```bash
composer show laravel/framework
composer why-not illuminate/console '^9.0|^10.0|^11.0|^12.0'
```

## Configuracao

Para uma instalacao simples, use `.env` como fallback:

```env
PULSE_BASE_URL=https://pulse.seu-dominio.com
PULSE_API_TOKEN=seu-token
PULSE_ROUTE_PREFIX=pulse
PULSE_MIDDLEWARE=web,auth
```

`PULSE_ROUTE_PREFIX` e `PULSE_MIDDLEWARE` controlam as rotas locais do aplicativo. Em ambientes com route cache, mantenha esses valores como configuracao da aplicacao, nao como configuracao por tenant.

## Multi-tenancy

Em apps multi-tenant, publique `config/pulse.php` e defina `credentials_resolver` para resolver `base_url` e `api_token` no runtime:

```php
'credentials_resolver' => App\Support\PulseTenantCredentials::class,
```

O resolver pode ser uma classe invokable, `Class@method`, callable ou classe com metodo `resolve`. Ele pode retornar:

- `null`, para cair no fallback do `.env`
- `array`, com `base_url`, `api_token`, `timeout` e/ou `options`
- `Sabbajohn\PulsePhp\PulseClient`, quando o app precisa montar o client inteiro

Exemplo:

```php
namespace App\Support;

class PulseTenantCredentials
{
    public function resolve(): ?array
    {
        $tenant = tenant();

        if (! $tenant) {
            return null;
        }

        return [
            'base_url' => $tenant->pulse_base_url,
            'api_token' => $tenant->pulse_api_token,
            'timeout' => 30,
        ];
    }
}
```

O `PulseClient` nao e registrado como singleton. A cada resolucao, a factory consulta o resolver atual; isso evita reutilizar token/base URL de outro tenant em requests ou jobs long-running.

## Uso

```php
use Sabbajohn\PulseLaravel\Facades\Pulse;

Pulse::emails()->sendAsync([
    'to' => [['email' => 'cliente@example.com']],
    'subject' => 'Processando',
    'html' => '<p>Recebemos sua solicitacao.</p>',
]);
```

Para chamadas fora do tenant atual, crie um cliente explicitamente:

```php
use Sabbajohn\PulseLaravel\PulseClientFactory;

$pulse = app(PulseClientFactory::class)->make([
    'base_url' => $tenant->pulse_base_url,
    'api_token' => $tenant->pulse_api_token,
]);
```

## UI e proxy local

A UI proxy local fica em `/{PULSE_ROUTE_PREFIX}`. O proxy aceita apenas os prefixos permitidos em `config/pulse.php` e deve permanecer protegido por middleware de autenticacao/autorizacao.

## Validacao

No projeto consumidor, rode:

```bash
php artisan test tests/Unit/PulsePhp tests/Feature/PulseLaravel
```
