<?php

namespace Tests\Feature\PulseLaravel;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Sabbajohn\PulseLaravel\Provisioning\PartnerProvisioner;
use Sabbajohn\PulseLaravel\Provisioning\ProcessProvisioningIntent;
use Sabbajohn\PulseLaravel\Provisioning\ProvisioningIntent;
use Sabbajohn\PulsePhp\PulseClient;
use Tests\TestCase;

class PartnerProvisioningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        (require base_path('../packages/sabbajohn/pulse-laravel/database/migrations/2026_10_21_000001_create_pulse_provisioning_intents.php'))->up();
        config(['pulse.provisioning.enabled' => true, 'pulse.provisioning.integration' => 'consumer-a']);
        Queue::fake();
    }

    public function test_dispatch_occurs_only_after_outer_commit_and_rollback_has_no_intent_or_http(): void
    {
        $provisioner = $this->provisioner([]);
        DB::beginTransaction();
        $intent = $provisioner->enqueue('client-1', ['name' => 'Client']);
        Queue::assertNothingPushed();
        $this->assertSame(1, ProvisioningIntent::count());
        DB::rollBack();
        $this->assertSame(0, ProvisioningIntent::count());
        Queue::assertNothingPushed();
        DB::beginTransaction();
        $intent = $provisioner->enqueue('client-1', ['name' => 'Client']);
        Queue::assertNothingPushed();
        DB::commit();
        Queue::assertPushed(ProcessProvisioningIntent::class, fn ($job) => $job->intentId === $intent->id);
        $this->assertSame('pending', $intent->fresh()->status);
    }

    public function test_durable_snapshot_identity_and_result_are_encrypted_and_duplicates_are_stable(): void
    {
        $history = [];
        $provisioner = $this->provisioner([$this->response()], $history);
        $intent = $provisioner->enqueue('client-1', ['name' => 'Client']);
        $duplicate = $provisioner->enqueue('client-1', ['name' => 'Client']);
        $this->assertSame($intent->id, $duplicate->id);
        $raw = DB::table('pulse_provisioning_intents')->first();
        $this->assertStringNotContainsString('Client', $raw->snapshot);
        $provisioner->process($intent->id);
        $provisioner->process($intent->id);
        $this->assertCount(1, $history);
        $this->assertSame('completed', $intent->fresh()->status);
        $this->assertSame('pulse_ws_test', $intent->fresh()->result['api_token']);
        $this->assertStringNotContainsString('pulse_ws_test', DB::table('pulse_provisioning_intents')->value('result'));
        $this->assertArrayNotHasKey('result', $intent->fresh()->toArray());
        $this->assertSame($intent->idempotency_key, $history[0]['request']->getHeaderLine('Idempotency-Key'));
        $this->expectException(\LogicException::class);
        $provisioner->enqueue('client-1', ['name' => 'Changed']);
    }

    public function test_timeout_retries_exact_snapshot_and_key_and_resumes_after_backoff(): void
    {
        $history = [];
        $provisioner = $this->provisioner([
            new ConnectException('Simulated timeout', new Request('POST', 'https://pulse.test')),
            $this->response(),
        ], $history);
        $intent = $provisioner->enqueue('client-1', ['name' => 'Client']);
        $provisioner->process($intent->id);
        $this->assertSame('pending', $intent->fresh()->status);
        $provisioner->process($intent->id);
        $this->assertCount(1, $history);
        $this->travel(31)->seconds();
        $provisioner->process($intent->id);
        $this->assertSame('completed', $intent->fresh()->status);
        $this->assertCount(2, $history);
        $this->assertSame((string) $history[0]['request']->getBody(), (string) $history[1]['request']->getBody());
        $this->assertSame($history[0]['request']->getHeaderLine('Idempotency-Key'), $history[1]['request']->getHeaderLine('Idempotency-Key'));
    }

    public function test_active_lease_blocks_a_second_worker_and_expired_lease_is_recovered(): void
    {
        $history = [];
        $provisioner = $this->provisioner([$this->response()], $history);
        $intent = $provisioner->enqueue('client-1', ['name' => 'Client']);
        $intent->update(['status' => 'processing', 'lease' => 'old-worker', 'leased_until' => now()->addSeconds(120)]);
        $provisioner->process($intent->id);
        $this->assertCount(0, $history);
        $this->travel(121)->seconds();
        $this->artisan('pulse:provision-pending')->assertSuccessful();
        Queue::assertPushed(ProcessProvisioningIntent::class);
        $provisioner->process($intent->id);
        $this->assertSame('completed', $intent->fresh()->status);
        $this->assertCount(1, $history);
    }

    public function test_terminal_conflict_does_not_retry_or_store_remote_error_text(): void
    {
        $provisioner = $this->provisioner([new Response(409, [], '{"message":"sensitive conflict"}')]);
        $intent = $provisioner->enqueue('client-1', ['name' => 'Client']);
        $provisioner->process($intent->id);
        $this->assertSame('failed', $intent->fresh()->status);
        $this->assertSame('http_409', $intent->fresh()->error_code);
        $this->assertStringNotContainsString('sensitive', json_encode($intent->fresh()->getAttributes()));
    }

    public function test_retryable_remote_errors_and_rate_limit_do_not_complete_intent(): void
    {
        $provisioner = $this->provisioner([new Response(429, [], '{}'), new Response(503, [], '{}'), $this->response()]);
        $intent = $provisioner->enqueue('client-1', ['name' => 'Client']);
        $provisioner->process($intent->id);
        $this->assertSame('pending', $intent->fresh()->status);
        $this->travel(31)->seconds();
        $provisioner->process($intent->id);
        $this->assertSame('pending', $intent->fresh()->status);
        $this->travel(61)->seconds();
        $provisioner->process($intent->id);
        $this->assertSame('completed', $intent->fresh()->status);
    }

    public function test_integration_namespace_and_disabled_flag_prevent_http(): void
    {
        $history = [];
        $provisioner = $this->provisioner([], $history);
        $intent = $provisioner->enqueue('client-1', ['name' => 'Client']);
        config(['pulse.provisioning.integration' => 'consumer-b']);
        $provisioner->process($intent->id);
        $other = $provisioner->enqueue('client-1', ['name' => 'Client']);
        $this->assertNotSame($intent->id, $other->id);
        $this->assertCount(0, $history);
        config(['pulse.provisioning.enabled' => false]);
        $this->artisan('pulse:provision-pending')->assertFailed();
        (new ProcessProvisioningIntent($intent->id))->handle($provisioner);
        $this->assertCount(0, $history);
        $this->expectException(\LogicException::class);
        $provisioner->enqueue('client-2', ['name' => 'Client']);
    }

    public function test_http_inside_a_transaction_is_explicitly_rejected(): void
    {
        $provisioner = $this->provisioner([]);
        $intent = $provisioner->enqueue('client-1', ['name' => 'Client']);
        DB::beginTransaction();
        try {
            $this->expectException(\LogicException::class);
            $provisioner->process($intent->id);
        } finally {
            DB::rollBack();
        }
    }

    public function test_queue_outage_keeps_committed_intent_for_scanner(): void
    {
        \Illuminate\Support\Facades\Bus::shouldReceive('dispatch')->once()
            ->andThrow(new \RuntimeException('Simulated queue outage'));
        $intent = app(PartnerProvisioner::class)->enqueue('client-1', ['name' => 'Client']);
        $this->assertSame('pending', $intent->fresh()->status);
        $this->assertSame(1, ProvisioningIntent::count());
    }

    public function test_stale_worker_cannot_overwrite_a_new_lease(): void
    {
        $intent = app(PartnerProvisioner::class)->enqueue('client-1', ['name' => 'Client']);
        $provisioner = $this->provisioner([function () use ($intent) {
            ProvisioningIntent::query()->whereKey($intent->id)->update([
                'lease' => 'new-lease', 'status' => 'processing', 'error_code' => 'new-worker',
            ]);

            return $this->response();
        }]);
        $provisioner->process($intent->id);
        $this->assertSame('new-lease', $intent->fresh()->lease);
        $this->assertSame('new-worker', $intent->fresh()->error_code);
        $this->assertNull($intent->fresh()->result);
    }

    public function test_credentials_resolver_never_falls_back_for_unready_customer(): void
    {
        $provisioner = $this->provisioner([$this->response()]);
        $intent = $provisioner->enqueue('client-1', ['name' => 'Client']);
        $provisioner->process($intent->id);
        try {
            $provisioner->credentialsFor('client-1');
            $this->fail('Pending activation must not resolve credentials.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('not ready', $e->getMessage());
        }
        $result = $intent->fresh()->result;
        $result['status'] = 'active';
        $intent->update(['result' => $result]);
        $this->assertSame('pulse_ws_test', $provisioner->credentialsFor('client-1')['api_token']);
        $this->expectException(\LogicException::class);
        $provisioner->credentialsFor('other-client');
    }

    public function test_case_distinct_namespaces_remain_isolated_under_nocase_collation(): void
    {
        $this->useNocaseNamespaceSchema();
        $manager = app(PartnerProvisioner::class);
        config(['pulse.provisioning.integration' => 'Consumer-A']);
        $upper = $manager->enqueue('client-1', ['name' => 'Client']);
        config(['pulse.provisioning.integration' => 'consumer-a']);
        $lower = $manager->enqueue('client-1', ['name' => 'Client']);
        $this->assertNotSame($upper->id, $lower->id);
        $this->assertNotSame($upper->integration_hash, $lower->integration_hash);
        $this->assertSame(2, DB::table('pulse_provisioning_intents')->where('integration', 'Consumer-A')->count());
        $result = ['external_customer_id' => 'client-1', 'account_id' => 1, 'workspace_id' => 2,
            'api_token' => 'upper-secret', 'status' => 'active'];
        $upper->update(['status' => 'completed', 'result' => $result]);
        $result['api_token'] = 'lower-secret';
        $lower->update(['status' => 'completed', 'result' => $result]);
        $this->assertSame('lower-secret', $manager->credentialsFor('client-1')['api_token']);
        config(['pulse.provisioning.integration' => 'Consumer-A']);
        $this->assertSame('upper-secret', $manager->credentialsFor('client-1')['api_token']);
        $upper->update(['status' => 'pending']);
        $lower->update(['status' => 'pending']);
        Queue::fake();
        $this->artisan('pulse:provision-pending')->assertSuccessful();
        Queue::assertPushed(ProcessProvisioningIntent::class, fn ($job) => $job->intentId === $upper->id);
        Queue::assertNotPushed(ProcessProvisioningIntent::class, fn ($job) => $job->intentId === $lower->id);
        $history = [];
        $this->provisioner([], $history)->process($lower->id);
        $this->assertCount(0, $history);
        // Corrupt a digest to match the requested namespace: binary original
        // comparison still prevents reads, dispatch and claims across names.
        $lower->update(['integration_hash' => $upper->integration_hash,
            'external_id_hash' => hash('sha256', 'foreign-client')]);
        $this->provisioner([], $history)->process($lower->id);
        $this->assertCount(0, $history);
        $this->assertSame('pending', $lower->fresh()->status);
        Queue::fake();
        $this->artisan('pulse:provision-pending')->assertSuccessful();
        Queue::assertNotPushed(ProcessProvisioningIntent::class, fn ($job) => $job->intentId === $lower->id);
        $this->expectException(\LogicException::class);
        $manager->credentialsFor('foreign-client');
    }

    public function test_all_resolution_scanning_and_worker_reads_use_primary_with_a_stale_replica(): void
    {
        $history = [];
        $manager = $this->provisioner([$this->response()], $history);
        $intent = $manager->enqueue('client-1', ['name' => 'Client']);
        $result = ['external_customer_id' => 'client-1', 'account_id' => 1, 'workspace_id' => 2,
            'api_token' => 'primary-only-secret', 'status' => 'active'];
        $intent->update(['status' => 'completed', 'result' => $result]);
        $connection = DB::connection();
        $connection->setReadPdo(new \PDO('sqlite::memory:'));
        $connection->forgetRecordModificationState();
        $connection->useWriteConnectionWhenReading(false);
        try {
            DB::table('pulse_provisioning_intents')->first();
            $this->fail('Replica fixture should not have the table.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('no such table', $e->getMessage());
        }
        $this->assertSame('primary-only-secret', $manager->credentialsFor('client-1')['api_token']);
        $intent->update(['status' => 'pending']);
        $connection->forgetRecordModificationState();
        Queue::fake();
        $this->artisan('pulse:provision-pending')->assertSuccessful();
        Queue::assertPushed(ProcessProvisioningIntent::class, fn ($job) => $job->intentId === $intent->id);
        $connection->forgetRecordModificationState();
        $manager->process($intent->id);
        $this->assertCount(1, $history);
        $this->assertSame('completed', ProvisioningIntent::query()->useWritePdo()->findOrFail($intent->id)->status);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('terminalNonJsonStatuses')]
    public function test_terminal_non_json_errors_do_not_schedule_endless_retries(int $status, string $body): void
    {
        $history = [];
        $manager = $this->provisioner([new Response($status, [], $body)], $history);
        $intent = $manager->enqueue('client-1', ['name' => 'Client']);
        $manager->process($intent->id);
        $this->assertSame('failed', $intent->fresh()->status);
        $this->assertSame('http_'.$status, $intent->fresh()->error_code);
        $this->travel(3601)->seconds();
        $manager->process($intent->id);
        $this->assertCount(1, $history);
    }

    public static function terminalNonJsonStatuses(): array
    {
        $cases = [];
        foreach ([401, 403, 404, 409, 422] as $status) {
            foreach (['<html>gateway error</html>', '{malformed'] as $body) {
                $cases[] = [$status, $body];
            }
        }

        return $cases;
    }

    public function test_transient_non_json_errors_honor_retry_after_seconds_and_http_date(): void
    {
        $history = [];
        $manager = $this->provisioner([new Response(429, ['Retry-After' => '120'], '<html>limit</html>'),
            new Response(503, ['Retry-After' => now()->addSeconds(300)->toRfc7231String()], '{bad'),
            $this->response()], $history);
        $intent = $manager->enqueue('client-1', ['name' => 'Client']);
        $manager->process($intent->id);
        $this->assertSame('pending', $intent->fresh()->status);
        $this->assertGreaterThanOrEqual(119, now()->diffInSeconds($intent->fresh()->available_at, false));
        $this->travel(121)->seconds();
        $manager->process($intent->id);
        $this->assertSame('pending', $intent->fresh()->status);
        $this->assertGreaterThanOrEqual(177, now()->diffInSeconds($intent->fresh()->available_at, false));
        $this->travel(180)->seconds();
        $manager->process($intent->id);
        $this->assertSame('completed', $intent->fresh()->status);
        $this->assertCount(3, $history);
    }

    private function useNocaseNamespaceSchema(): void
    {
        $sql = DB::table('sqlite_master')->where('type', 'table')->where('name', 'pulse_provisioning_intents')->value('sql');
        $indexes = DB::table('sqlite_master')->where('type', 'index')->where('tbl_name', 'pulse_provisioning_intents')
            ->whereNotNull('sql')->pluck('sql');
        $modified = str_replace('"integration" varchar', '"integration" varchar COLLATE NOCASE', $sql);
        $this->assertNotSame($sql, $modified);
        // Existing guard proves this is the newly-created :memory: database.
        DB::statement('DROP TABLE pulse_provisioning_intents');
        DB::statement($modified);
        foreach ($indexes as $index) {
            DB::statement($index);
        }
    }

    private function response(): Response
    {
        return new Response(201, [], json_encode(['external_customer_id' => 'client-1', 'account_id' => 1,
            'workspace_id' => 2, 'api_token' => 'pulse_ws_test', 'status' => 'pending_activation']));
    }

    private function provisioner(array $responses, ?array &$history = null): PartnerProvisioner
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        if (is_array($history)) {
            $stack->push(Middleware::history($history));
        }
        $http = new Client(['base_uri' => 'https://pulse.test/api/v2/', 'handler' => $stack, 'http_errors' => false]);
        $client = new PulseClient('https://pulse.test', 'test-partner', [], $http);

        return new class($client) extends PartnerProvisioner
        {
            public function __construct(private PulseClient $mock) {}

            protected function client(int $timeout): PulseClient
            {
                return $this->mock;
            }
        };
    }
}
