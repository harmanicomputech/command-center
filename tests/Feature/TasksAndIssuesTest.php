<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Photo;
use App\Models\Task;
use App\Models\TaskReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\LoadsRegister;
use Tests\TestCase;

class TasksAndIssuesTest extends TestCase
{
    use LoadsRegister;
    use RefreshDatabase;

    private User $coordinator;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->loadSmallRegister();
        $ward = $this->ward('Izzi Ward 01');
        $this->coordinator = User::factory()->coordinator($ward)->create();
        $this->agent = User::factory()->agent($ward)->create(['name' => 'Field Agent']);
    }

    private function sync(User $user, string $type, array $payload, ?string $uuid = null)
    {
        return $this->actingAs($user)->postJson('/api/field/sync', ['items' => [['id' => $uuid ?? (string) Str::uuid(), 'type' => $type, 'payload' => $payload]]]);
    }

    public function test_coordinators_set_tasks_in_their_ward_and_agents_report_progress(): void
    {
        $this->actingAs($this->coordinator);
        $other = User::factory()->agent($this->ward('Izzi Ward 02'))->create();
        $this->post('/tasks', ['title' => 'Door-to-door', 'type' => 'door_to_door', 'ward_id' => $this->ward('Izzi Ward 02')->id, 'proof' => 'count'])->assertSessionHasErrors('ward_id');
        $this->post('/tasks', ['title' => 'Door-to-door', 'type' => 'door_to_door', 'ward_id' => $this->ward('Izzi Ward 01')->id, 'assignee_id' => $other->id, 'proof' => 'count'])->assertSessionHasErrors('assignee_id');
        $this->post('/tasks', ['title' => 'Door-to-door on Market Road', 'type' => 'door_to_door', 'ward_id' => $this->ward('Izzi Ward 01')->id, 'target' => 50, 'target_unit' => 'households', 'proof' => 'photo', 'due_on' => now()->addDays(2)->format('Y-m-d')])->assertRedirect();
        $task = Task::sole();

        $this->actingAs($this->agent)->get('/field/tasks')->assertOk()->assertSee('Door-to-door on Market Road')->assertSee('Photo needed');
        $this->get("/field/tasks/{$task->id}")->assertOk()->assertSee('Add an update');
        $this->actingAs($other)->get("/field/tasks/{$task->id}")->assertNotFound();

        $uuid = (string) Str::uuid();
        $this->sync($this->agent, 'task_report', ['task_id' => $task->id, 'count' => 20])->assertJsonPath('results.0.status', 'ok');
        $this->sync($this->agent, 'task_report', ['task_id' => $task->id, 'count' => 30, 'done' => true])->assertJsonPath('results.0.status', 'invalid')->assertJsonPath('results.0.message', 'This task needs a photo as proof.');
        $this->sync($this->agent, 'task_report', ['task_id' => $task->id, 'count' => 30, 'done' => true, 'has_photo' => true], $uuid)->assertJsonPath('results.0.status', 'ok');
        $this->sync($this->agent, 'task_report', ['task_id' => $task->id, 'count' => 30, 'done' => true, 'has_photo' => true], $uuid);
        $this->sync($other, 'task_report', ['task_id' => $task->id, 'count' => 1])->assertJsonPath('results.0.status', 'invalid');
        $this->assertSame(2, TaskReport::count());
        $this->assertSame(50, $task->fresh()->reports->sum('count'));

        // The photo follows: refused before its report exists, stored after.
        $this->actingAs($this->agent);
        $photo = UploadedFile::fake()->image('proof.jpg', 3000, 2000);
        $this->post('/api/field/photos', ['uuid' => (string) Str::uuid(), 'owner_type' => 'task_report', 'owner_uuid' => (string) Str::uuid(), 'photo' => $photo], ['Accept' => 'application/json'])->assertStatus(409);
        $this->post('/api/field/photos', ['uuid' => $photoUuid = (string) Str::uuid(), 'owner_type' => 'task_report', 'owner_uuid' => $uuid, 'photo' => $photo], ['Accept' => 'application/json'])->assertOk();
        $this->post('/api/field/photos', ['uuid' => $photoUuid, 'owner_type' => 'task_report', 'owner_uuid' => $uuid, 'photo' => $photo], ['Accept' => 'application/json'])->assertOk();

        $stored = Photo::sole();
        $this->assertSame(1600, $stored->width, 'The long side is capped.');
        Storage::disk('local')->assertExists([$stored->path, $stored->thumb_path]);

        $this->actingAs($this->coordinator)->get("/tasks/{$task->id}")->assertOk()->assertSee('Field Agent')->assertSee($stored->url(true));
        $this->get($stored->url(true))->assertOk();
        $this->actingAs(User::factory()->coordinator($this->ward('Abakaliki Ward 01'))->create())->get($stored->url())->assertForbidden();
        $this->get("/tasks/{$task->id}")->assertForbidden();
    }

    public function test_issues_are_reported_offline_first_and_reviewed(): void
    {
        $uuid = (string) Str::uuid();
        $payload = ['category' => 'road', 'description' => 'Erosion has cut the road to the health centre.', 'severity' => 'high', 'people_affected' => 400, 'ward_id' => $this->ward('Izzi Ward 02')->id, 'community' => 'Sample village'];

        $this->sync($this->agent, 'issue', [...$payload, 'description' => 'x'])->assertJsonPath('results.0.status', 'invalid');
        $this->sync($this->agent, 'issue', [...$payload, 'ward_id' => $this->ward('Abakaliki Ward 01')->id])->assertJsonPath('results.0.status', 'invalid');
        $this->sync($this->agent, 'issue', $payload, $uuid)->assertJsonPath('results.0.status', 'ok');
        $this->sync($this->agent, 'issue', $payload, $uuid);
        $issue = Issue::sole();
        $this->assertSame(['new', $this->agent->id], [$issue->status, $issue->reported_by]);

        $this->post('/api/field/photos', ['uuid' => (string) Str::uuid(), 'owner_type' => 'issue', 'owner_uuid' => $uuid, 'photo' => UploadedFile::fake()->image('road.png', 800, 600)], ['Accept' => 'application/json'])->assertOk();
        $this->get('/field/issues')->assertOk()->assertSee('Sample village');

        $this->actingAs(User::factory()->lgaLeader($this->lga('Izzi')->id)->create());
        $this->get('/issues')->assertOk()->assertSee('Erosion has cut the road')->assertSee('Bad road');
        $this->post("/issues/{$issue->id}/status", ['status' => 'used'])->assertSessionHas('status');
        $this->assertSame('used', $issue->fresh()->status);
        $this->get('/issues/brief')->assertOk()->assertSee('Top issues by LGA')->assertSee('Izzi');

        $this->actingAs(User::factory()->coordinator($this->ward('Abakaliki Ward 01'))->create());
        $this->get('/issues')->assertDontSee('Erosion has cut the road');
        $this->post("/issues/{$issue->id}/status", ['status' => 'noted'])->assertForbidden();
    }
}
