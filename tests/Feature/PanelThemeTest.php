<?php

namespace Tests\Feature;

use App\Support\PanelTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_theme_defaults_to_lux_when_unset(): void
    {
        config(['deltasupport.panel_theme' => 'lux']);

        $this->assertSame(PanelTheme::LUX, PanelTheme::current());
        $this->assertTrue(PanelTheme::isLux());
        $this->assertFalse(PanelTheme::isV2());
    }

    public function test_panel_theme_v2_is_server_controlled(): void
    {
        config(['deltasupport.panel_theme' => 'v2']);

        $this->assertSame(PanelTheme::V2, PanelTheme::current());
        $this->assertTrue(PanelTheme::isV2());
        $this->assertFalse(PanelTheme::isLux());
    }

    public function test_invalid_panel_theme_falls_back_to_lux(): void
    {
        config(['deltasupport.panel_theme' => 'hacked']);

        $this->assertSame(PanelTheme::LUX, PanelTheme::current());
    }
}
