<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The Filament panel is useless without its published assets: the login page
 * renders broken HTML and Livewire never wires up (submit does nothing).
 * This test asserts the `filament:assets` output files exist — it would have
 * caught the missing publish step in the Dockerfile (served 404s at runtime).
 */
class FilamentAssetsTest extends TestCase
{
    public function test_filament_css_is_published(): void
    {
        $this->assertFileExists(public_path('css/filament/filament/app.css'));
    }

    public function test_filament_js_is_published(): void
    {
        $this->assertFileExists(public_path('js/filament/filament/app.js'));
    }

    public function test_filament_fonts_are_published(): void
    {
        $this->assertFileExists(public_path('fonts/filament/filament/inter/index.css'));
    }
}
