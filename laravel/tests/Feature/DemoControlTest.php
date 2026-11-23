<?php

namespace Tests\Feature;

use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DemoControlTest — control remoto de la demo (celular → Mission Control):
 * - sin DEMO_TOKEN todo el modo demo es 404 y /status.json no cambia
 * - con token, solo la clave correcta opera
 * - el estado deseado viaja en /status.json (clave `demo`) y no toca la BD real
 */
class DemoControlTest extends TestCase
{
    use RefreshDatabase;

    private function site(string $name): Site
    {
        return Site::create([
            'url' => "http://{$name}",
            'name' => $name,
            'type' => 'wp',
            'wp_user' => 'monitor',
            'ap_token' => null,
            'consecutive_failures' => 0,
            'current_state' => 'wp-full',
        ]);
    }

    public function test_demo_is_disabled_without_token(): void
    {
        $this->site('a.test');

        $this->get('/demo?k=x')->assertNotFound();
        $this->get('/demo/state?k=x')->assertNotFound();
        $this->postJson('/demo/command?k=x', ['reset' => true])->assertNotFound();
        $this->getJson('/status.json')->assertJsonMissingPath('demo');
    }

    public function test_wrong_or_missing_key_is_rejected(): void
    {
        config(['mediadev.demo_token' => 'secret']);
        $site = $this->site('a.test');

        $this->get('/demo')->assertNotFound();
        $this->get('/demo?k=nope')->assertNotFound();
        $this->postJson('/demo/command?k=nope', ['ids' => [$site->id], 'state' => 'down'])->assertNotFound();
        $this->getJson('/status.json')->assertJsonPath('demo.sites', []);
    }

    public function test_panel_renders_all_sites_with_the_right_key(): void
    {
        config(['mediadev.demo_token' => 'secret']);
        $this->site('a.test');
        $this->site('b.test');

        $this->get('/demo?k=secret')->assertOk()->assertSee('a.test')->assertSee('b.test');
    }

    public function test_command_sets_state_and_status_json_exposes_it_without_touching_the_db(): void
    {
        config(['mediadev.demo_token' => 'secret']);
        $site = $this->site('a.test');
        $other = $this->site('b.test');

        $this->postJson('/demo/command?k=secret', ['ids' => [$site->id], 'state' => 'down'])
            ->assertOk()
            ->assertJsonPath("sites.{$site->id}", 'down');

        $this->postJson('/demo/command?k=secret', ['ids' => [$other->id], 'state' => 'wp-degraded'])->assertOk();

        $this->getJson('/status.json')
            ->assertJsonPath('demo.seq', 2)
            ->assertJsonPath("demo.sites.{$site->id}", 'down')
            ->assertJsonPath("demo.sites.{$other->id}", 'wp-degraded');

        $this->assertSame('wp-full', $site->fresh()->current_state->value);
    }

    public function test_null_state_heals_one_site_and_reset_heals_all(): void
    {
        config(['mediadev.demo_token' => 'secret']);
        $a = $this->site('a.test');
        $b = $this->site('b.test');
        $this->postJson('/demo/command?k=secret', ['ids' => [$a->id, $b->id], 'state' => 'down']);

        $this->postJson('/demo/command?k=secret', ['ids' => [$a->id], 'state' => null])
            ->assertJsonMissingPath("sites.{$a->id}")
            ->assertJsonPath("sites.{$b->id}", 'down');

        $this->postJson('/demo/command?k=secret', ['reset' => true])->assertJsonPath('sites', []);
    }

    public function test_invalid_state_is_rejected_and_unknown_ids_are_ignored(): void
    {
        config(['mediadev.demo_token' => 'secret']);
        $site = $this->site('a.test');

        $this->postJson('/demo/command?k=secret', ['ids' => [$site->id], 'state' => 'exploded'])->assertStatus(422);

        $this->postJson('/demo/command?k=secret', ['ids' => [9999], 'state' => 'down'])
            ->assertOk()
            ->assertJsonPath('sites', []);
    }
}
