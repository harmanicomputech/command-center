<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Issue;
use App\Models\NarrativeReport;
use App\Models\Photo;
use App\Models\TaskReport;
use App\Models\User;
use App\Models\Voter;
use App\Services\PhotoStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Photos: uploaded from the outbox (after the record they belong to), and
 * shown only to people who can see that record. Never cached publicly.
 */
class PhotoController extends Controller
{
    /** Outbox owner types → [model, column holding the uploader]. */
    private const OWNERS = [
        'task_report' => [TaskReport::class, 'user_id'],
        'issue' => [Issue::class, 'reported_by'],
        'narrative_report' => [NarrativeReport::class, 'reported_by'],
        'voter' => [Voter::class, 'captured_by'],
    ];

    public function upload(Request $request, PhotoStore $store): JsonResponse
    {
        $data = $request->validate([
            'uuid' => ['required', 'uuid'],
            'owner_type' => ['required', 'in:'.implode(',', array_keys(self::OWNERS))],
            'owner_uuid' => ['required', 'uuid'],
            'photo' => ['required', 'file', 'max:'.config('field.photo_max_kb')],
        ]);

        [$model, $column] = self::OWNERS[$data['owner_type']];
        $owner = $model::query()->where('uuid', strtolower($data['owner_uuid']))->first();

        // The record hasn't synced yet: the outbox tries again later.
        if (! $owner) {
            return response()->json(['status' => 'error', 'message' => 'Waiting for its record to sync.'], 409);
        }

        abort_unless($owner->{$column} === $request->user()->id, 403, 'You can only add photos to your own reports.');

        if ($owner instanceof Voter) {
            return $this->storeFace($request, $owner, strtolower($data['uuid']), $store);
        }

        $photo = $store->store($request->file('photo'), $owner, strtolower($data['uuid']), $request->user());

        return response()->json(['status' => 'ok', 'id' => $photo->id]);
    }

    public function show(Request $request, Photo $photo, ?string $size = null): StreamedResponse
    {
        abort_unless($this->canSee($request->user(), $photo), 403);
        $path = $size === 'thumb' ? $photo->thumb_path : $photo->path;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, Str::afterLast($path, '/'), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * A voter's face photo from the face check: one per registration (a
     * retake replaces the old one). Its arrival lets a passed check earn
     * points. Erased records take no photo.
     */
    private function storeFace(Request $request, Voter $voter, string $uuid, PhotoStore $store): JsonResponse
    {
        if ($voter->erased_at !== null) {
            return response()->json(['status' => 'ok', 'message' => 'This registration was erased.']);
        }

        $photo = $store->store($request->file('photo'), $voter, $uuid, $request->user());
        Photo::query()->where('owner_type', 'voter')->where('owner_id', $voter->id)->whereKeyNot($photo->id)->get()->each(fn (Photo $old) => $store->delete($old));
        $voter->forceFill(['face_photo_at' => $voter->face_photo_at ?? now()])->save();

        return response()->json(['status' => 'ok', 'id' => $photo->id]);
    }

    /**
     * Staff add photos to an event from the Command Center.
     */
    public function storeForEvent(Request $request, Event $event, PhotoStore $store)
    {
        abort_unless(Event::query()->visibleTo($request->user())->whereKey($event->id)->exists(), 403);
        $request->validate(['photos' => ['required', 'array', 'max:10'], 'photos.*' => ['file', 'max:'.config('field.photo_max_kb')]]);

        foreach ($request->file('photos') as $file) {
            $store->store($file, $event, (string) Str::uuid(), $request->user());
        }

        return back()->with('status', 'Photos added.');
    }

    /**
     * The media team attaches a screenshot to a narrative report.
     */
    public function storeForNarrativeReport(Request $request, NarrativeReport $report, PhotoStore $store)
    {
        abort_unless(NarrativeReport::query()->visibleTo($request->user())->whereKey($report->id)->exists(), 403);
        $request->validate(['photo' => ['required', 'file', 'max:'.config('field.photo_max_kb')]]);
        $store->store($request->file('photo'), $report, (string) Str::uuid(), $request->user());

        return back()->with('status', 'Screenshot added.');
    }

    private function canSee(User $user, Photo $photo): bool
    {
        $owner = $photo->owner;

        return match (true) {
            $owner instanceof TaskReport => $owner->user_id === $user->id || ($owner->task?->ward && ! $user->role->usesFieldApp() && $user->canSeeWard($owner->task->ward)),
            $owner instanceof Issue => Issue::query()->visibleTo($user)->whereKey($owner->id)->exists(),
            $owner instanceof Event => Event::query()->visibleTo($user)->whereKey($owner->id)->exists(),
            $owner instanceof NarrativeReport => NarrativeReport::query()->visibleTo($user)->whereKey($owner->id)->exists(),
            // Face photos: staff in the voter's area only (not agents, even the one who took it).
            $owner instanceof Voter => ! $user->role->usesFieldApp() && $owner->erased_at === null && Voter::query()->inAreaOf($user)->whereKey($owner->id)->exists(),
            default => false,
        };
    }
}
