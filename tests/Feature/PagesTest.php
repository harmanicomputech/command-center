<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PollingUnitImporter;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
    }

    public function test_every_command_center_page_renders_empty_and_with_the_register(): void
    {
        $this->actingAs(User::factory()->admin()->create(['name' => 'Ada Admin']));
        $pages = ['/', '/brief', '/areas', '/segments', '/surveys', '/surveys/create', '/field/surveys', '/results', '/presets', '/tasks', '/issues', '/issues/brief', '/leaderboard', '/people', '/structure', '/influence', '/events', '/voters', '/team', '/notifications', '/users', '/settings', '/system', '/audit', '/design', '/account', '/field', '/field/me', '/field/register', '/field/tasks', '/field/issues', '/field/leaderboard', '/field/outbox', '/field/registrations'];

        foreach ($pages as $page) {
            $this->get($page)->assertOk();
        }

        app(PollingUnitImporter::class)->import(PollingUnitImporter::bundledPath());

        foreach ($pages as $page) {
            $this->get($page)->assertOk();
        }

        $this->get('/')->assertSee('Good')->assertSee('Finish setting up');
        $this->get('/areas?all=1')->assertSee('Ohaukwu')->assertSee('Ohaukwu Ward 01')->assertSee('Priority');
        $this->get('/areas/ohaukwu')->assertOk()->assertSee('Ohaukwu Ward 01');
        $this->get('/design')->assertSee('Design system')->assertSee('--brand');
    }

    public function test_the_pwa_files_are_served(): void
    {
        $manifest = $this->get('/manifest.webmanifest')->assertOk()->assertHeader('Content-Type', 'application/manifest+json')->json();

        $this->assertSame('Command Center', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));
        $this->assertSame(['Register voter', 'Report issue', 'My tasks'], array_column($manifest['shortcuts'], 'name'));

        foreach ($manifest['icons'] as $icon) {
            [$width] = getimagesize(public_path(ltrim($icon['src'], '/')));
            $this->assertSame((int) explode('x', $icon['sizes'])[0], $width);
        }

        $sw = file_get_contents(public_path('sw.js'));
        $this->assertMatchesRegularExpression("/const VERSION = '[^']+';/", $sw);
        $this->assertStringNotContainsString('/users', $sw, 'The service worker must never cache pages with phone numbers.');
        $this->get('/offline')->assertOk()->assertSee('You’re offline', false);
    }
}
