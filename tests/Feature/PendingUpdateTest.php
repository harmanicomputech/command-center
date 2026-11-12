<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Migrations;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * After an upload, new code can need database changes that haven't run.
 * Admins must always reach Update database; nobody sees a raw error.
 */
class PendingUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        Migrations::forget();

        // A page whose query needs a column the waiting update adds.
        Route::middleware(['web', 'auth'])->get('/needs-new-column', fn () => throw new QueryException('sqlite', 'select new_column from voters', [], new \Exception('no such column: new_column')));
    }

    protected function tearDown(): void
    {
        Migrations::forget();
        parent::tearDown();
    }

    private function pretendAnUpdateIsWaiting(): void
    {
        DB::table('migrations')->orderByDesc('id')->limit(1)->delete();
        Migrations::forget();
    }

    public function test_with_nothing_waiting_the_system_page_opens_as_usual(): void
    {
        $this->actingAs($this->admin)->get('/system')->assertOk()->assertSee('Update database');
        $this->actingAs($this->admin)->get('/system/update')->assertRedirect('/system');
    }

    public function test_admins_are_sent_to_update_database_instead_of_an_error(): void
    {
        $this->pretendAnUpdateIsWaiting();

        $this->actingAs($this->admin)->get('/system')->assertRedirect(route('system.update'));
        $this->actingAs($this->admin)->get('/system/update')->assertOk()->assertSee('Update the database')->assertSee('1 update waiting');
        $this->actingAs($this->admin)->get('/needs-new-column')->assertRedirect(route('system.update'));
    }

    public function test_everyone_else_sees_that_the_app_is_being_updated(): void
    {
        $this->pretendAnUpdateIsWaiting();
        $strategist = User::factory()->strategist()->create();

        $this->actingAs($strategist)->get('/needs-new-column')->assertStatus(503)->assertSee('The app is being updated');
        $this->actingAs($strategist)->getJson('/needs-new-column')->assertStatus(503)->assertJsonPath('message', 'The app is being updated. Try again in a few minutes.');
        $this->actingAs($strategist)->get('/system/update')->assertForbidden();
    }

    public function test_a_real_database_error_is_still_reported_when_nothing_is_waiting(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(QueryException::class);

        $this->actingAs($this->admin)->get('/needs-new-column');
    }
}
