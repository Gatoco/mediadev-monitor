<?php

namespace Tests\Feature;

use App\Models\ChaosExperiment;
use App\Models\NotificationLog;
use App\Models\Site;
use App\Models\UptimeCheck;
use App\Services\Chaos\ChaosLab;
use App\Services\Chaos\ContainerAgent;
use App\Services\Chaos\FaultModes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChaosLabTest extends TestCase
{
    use RefreshDatabase;

    private string $modesFile;

    /** @var array<int, string> */
    public array $calls = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->modesFile = storage_path('framework/testing/chaos-modes-' . uniqid() . '.json');
        config(['chaos.enabled' => true]);

        $test = $this;
        $this->app->instance(ContainerAgent::class, new class($test) extends ContainerAgent {
            public function __construct(private ChaosLabTest $test)
            {
            }

            public function states(): array
            {
                return ['mediadev-wp-full' => 'running', 'mediadev-portfolio' => 'running'];
            }

            public function act(string $container, string $action): void
            {
                $this->test->calls[] = "{$action} {$container}";
            }
        });
        $this->app->instance(FaultModes::class, new FaultModes($this->modesFile));
    }

    public function tearDown(): void
    {
        @unlink($this->modesFile);
        parent::tearDown();
    }

    private function lab(): ChaosLab
    {
        return app(ChaosLab::class);
    }

    private function check(Site $site, ?int $status, string $ago): void
    {
        UptimeCheck::create(['site_id' => $site->id, 'status' => $status, 'response_ms' => 10, 'ts' => now()->modify($ago)]);
    }

    public function test_lab_is_hidden_unless_enabled(): void
    {
        config(['chaos.enabled' => false]);

        $this->get('/lab')->assertNotFound();
        $this->get('/lab/api/state')->assertNotFound();
    }

    public function test_setting_a_mode_writes_the_shared_file_and_opens_an_experiment(): void
    {
        $experiment = $this->lab()->setMode('hubot.cl', 'error500');

        $this->assertSame(['hubot.cl' => 'error500'], json_decode(file_get_contents($this->modesFile), true));
        $this->assertSame('error500', $experiment->scenario);
        $this->assertSame('down', $experiment->expected);

        $this->lab()->setMode('hubot.cl', 'normal');
        $this->assertSame([], json_decode(file_get_contents($this->modesFile), true));
        $this->assertNotNull($experiment->fresh()->restored_at);
    }

    public function test_rejects_hosts_outside_the_emulator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->lab()->setMode('produccion-real.cl', 'caido');
    }

    public function test_full_down_experiment_is_measured_and_passes(): void
    {
        $site = Site::create(['url' => 'http://hubot.cl:80', 'name' => 'hubot.cl', 'current_state' => 'wp-full']);

        $this->travel(-20)->minutes();
        $experiment = $this->lab()->setMode('hubot.cl', 'error500');
        $this->travelBack();

        // Tres chequeos fallidos después de la inyección + alerta enviada.
        $this->check($site, 500, '-15 minutes');
        $this->check($site, 500, '-10 minutes');
        $this->check($site, 500, '-5 minutes');
        $site->update(['current_state' => 'down', 'consecutive_failures' => 3]);
        NotificationLog::create(['site_id' => $site->id, 'kind' => 'down', 'site_name' => 'hubot.cl', 'text' => 'caído', 'channels' => 'telegram', 'delivered' => true, 'sent_at' => now()->subMinutes(4)]);

        $experiment = $this->lab()->refresh($experiment);
        $this->assertNotNull($experiment->detected_at);
        $this->assertNotNull($experiment->alerted_at);
        $this->assertSame('running', $experiment->status);
        $this->assertSame(15, $this->lab()->describe($experiment)['detection_minutes']);

        // Restaurar → sigue en curso hasta que un chequeo vuelva a responder.
        $experiment = $this->lab()->finish($experiment);
        $this->assertSame('running', $experiment->status);
        $this->assertSame([], json_decode(file_get_contents($this->modesFile), true));

        $this->travel(1)->minutes();
        $this->check($site, 200, 'now');
        $experiment = $this->lab()->refresh($experiment);

        $this->assertSame('finished', $experiment->status);
        $this->assertSame('pass', $experiment->result);
        $this->assertNotNull($experiment->recovered_at);
    }

    public function test_intermittent_fails_if_monitor_alerts(): void
    {
        $site = Site::create(['url' => 'http://itma.cl:80', 'name' => 'itma.cl', 'current_state' => 'wp-full']);
        $experiment = $this->lab()->runScenario('intermitente', 'itma.cl');

        NotificationLog::create(['site_id' => $site->id, 'kind' => 'down', 'site_name' => 'itma.cl', 'text' => 'caído', 'channels' => '', 'delivered' => false, 'sent_at' => now()]);

        $experiment = $this->lab()->finish($experiment);

        $this->assertSame('finished', $experiment->status);
        $this->assertSame('fail', $experiment->result);
    }

    public function test_stopping_a_container_goes_through_the_agent(): void
    {
        $experiment = $this->lab()->stopContainer('wp-full');

        $this->assertSame(['stop mediadev-wp-full'], $this->calls);
        $this->assertSame('container', $experiment->target_type);
        $this->assertSame(['wp-full'], $experiment->targets);

        $this->lab()->startContainer('wp-full');
        $this->assertSame(['stop mediadev-wp-full', 'start mediadev-wp-full'], $this->calls);
        $this->assertNotNull(ChaosExperiment::find($experiment->id)->restored_at);
    }

    public function test_unknown_container_is_rejected(): void
    {
        $this->postJson('/lab/api/containers/mysql/stop')->assertStatus(422);
        $this->assertSame([], $this->calls);
    }

    public function test_state_endpoint_reports_containers_and_sites(): void
    {
        Site::create(['url' => 'http://hubot.cl:80', 'name' => 'hubot.cl', 'current_state' => 'down']);

        $this->getJson('/lab/api/state')
            ->assertOk()
            ->assertJsonPath('agent_ok', true)
            ->assertJsonFragment(['service' => 'portfolio-emulator', 'monitor_state' => 'down'])
            ->assertJsonFragment(['host' => 'hubot.cl', 'monitor_state' => 'down', 'mode' => 'normal']);
    }

    public function test_lab_page_renders(): void
    {
        $this->get('/lab')->assertOk()->assertSee('Chaos Lab');
    }

    public function test_app_api_requires_its_token(): void
    {
        config(['chaos.app_token' => 'secreto']);

        $this->getJson('/lab/app/containers')->assertStatus(401);
        $this->getJson('/lab/app/containers', ['Authorization' => 'Bearer otro'])->assertStatus(401);
        $this->postJson('/lab/app/containers/wp-full/stop')->assertStatus(401);
        $this->assertSame([], $this->calls);
    }

    public function test_app_api_is_closed_when_no_token_is_configured(): void
    {
        config(['chaos.app_token' => '']);

        $this->getJson('/lab/app/containers', ['Authorization' => 'Bearer '])->assertStatus(401);
    }

    public function test_app_lists_and_toggles_containers_without_csrf(): void
    {
        config(['chaos.app_token' => 'secreto']);
        $auth = ['Authorization' => 'Bearer secreto'];

        $this->getJson('/lab/app/containers', $auth)
            ->assertOk()
            ->assertJsonPath('agent_ok', true)
            ->assertJsonFragment(['service' => 'wp-full', 'container' => 'mediadev-wp-full', 'container_state' => 'running'])
            ->assertJsonFragment(['service' => 'portfolio-down']);

        $this->postJson('/lab/app/containers/wp-full/stop', [], $auth)->assertOk();
        $this->postJson('/lab/app/containers/wp-full/start', [], $auth)->assertOk();
        $this->postJson('/lab/app/containers/wp-full/restart', [], $auth)->assertNotFound();
        $this->postJson('/lab/app/containers/mysql/stop', [], $auth)->assertStatus(422);

        $this->assertSame(['stop mediadev-wp-full', 'start mediadev-wp-full'], $this->calls);
    }
}
