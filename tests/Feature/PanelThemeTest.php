<?php

namespace Tests\Feature;

use App\Support\PanelTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_theme_is_always_v2(): void
    {
        $this->assertSame(PanelTheme::V2, PanelTheme::current());
        $this->assertTrue(PanelTheme::isV2());
        $this->assertFalse(PanelTheme::isLux());
    }

    public function test_panel_theme_ignores_config_overrides(): void
    {
        config(['deltasupport.panel_theme' => 'lux']);

        $this->assertSame(PanelTheme::V2, PanelTheme::current());
        $this->assertTrue(PanelTheme::isV2());
    }
}
