<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
    }

    public function test_placeholders_apply_until_the_owner_saves_real_values(): void
    {
        $this->assertSame(20, Settings::int('target.agent_daily'));
        $this->assertSame('Command Center', Settings::get('campaign.name'));

        $this->actingAs(User::factory()->admin()->create());
        $this->get('/settings')->assertOk()->assertSee('Registration targets');

        $this->put('/settings/targets', ['target__agent_daily' => '0', 'target__ward_daily' => '100', 'target__total' => '500000'])->assertSessionHasErrors('target__agent_daily');
        $this->put('/settings/targets', ['target__agent_daily' => '35', 'target__ward_daily' => '100', 'target__total' => '500000'])->assertSessionHas('status', 'Registration targets saved.');
        $this->assertSame(35, Settings::int('target.agent_daily'));

        $this->put('/settings/campaign', ['campaign__name' => 'Ebonyi Forward', 'campaign__candidate' => '', 'campaign__party' => ''])->assertRedirect();
        $this->get('/settings')->assertSee('Ebonyi Forward');
        $this->assertSame('Ebonyi Forward', $this->get('/manifest.webmanifest')->json('name'));

        $this->assertSame(2, AuditLog::where('action', 'settings.update')->count());
        $this->put('/settings/nope', [])->assertNotFound();
    }
}
