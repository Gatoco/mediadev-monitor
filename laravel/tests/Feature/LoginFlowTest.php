<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Real login flow through the Filament component (not just Auth::attempt):
 * fills the Livewire form and calls authenticate(). This catches broken
 * wiring (missing assets, wrong form state path) that unit tests miss.
 */
class LoginFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_pass_login(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@mediadev.cl',
            'password' => bcrypt('secret123'),
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@mediadev.cl',
                'password' => 'secret123',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticated();
    }

    public function test_invalid_credentials_rejected(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@mediadev.cl',
            'password' => bcrypt('secret123'),
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@mediadev.cl',
                'password' => 'wrong-password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors();

        $this->assertGuest();
    }
}
