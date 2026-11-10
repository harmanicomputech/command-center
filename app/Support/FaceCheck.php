<?php

namespace App\Support;

/**
 * The face check on voter registration: whether it's required (Settings),
 * and where the phone finds the face engine the build copies to
 * public/build/face/<version>/.
 *
 * A registration's face_status:
 *   passed        the voter followed the prompts on camera (turn, smile, blink)
 *   photo_only    a plain photo: the phone couldn't run the check, or it didn't pass
 *   missing       sent without a face check (an old app version, or no scripts)
 *   not_required  the check was off, or the record is older than the check
 *
 * Before verification, a registration earns points only when its face check
 * passed and its photo has arrived, or the check wasn't required.
 */
class FaceCheck
{
    public const STATUSES = [
        'passed' => ['label' => 'Face check passed', 'tone' => 'good'],
        'photo_only' => ['label' => 'Photo only', 'tone' => 'warn'],
        'missing' => ['label' => 'No face check', 'tone' => 'bad'],
        'not_required' => ['label' => 'Not required', 'tone' => null],
    ];

    public static function required(): bool
    {
        return Settings::get('canvass.face_check', 'required') !== 'off';
    }

    /**
     * What the registration page passes to the faceCheck component, or null
     * when the check is off.
     *
     * @return array{base: string, files: list<string>, bytes: int}|null
     */
    public static function options(): ?array
    {
        if (! self::required()) {
            return null;
        }

        $engine = config('field.face_engine');
        $dir = public_path("build/face/{$engine['version']}");
        $bytes = collect($engine['files'])->sum(fn (string $file) => is_file("{$dir}/{$file}") ? filesize("{$dir}/{$file}") : 0);

        return [
            'base' => "/build/face/{$engine['version']}/",
            'files' => $engine['files'],
            'bytes' => $bytes ?: $engine['bytes'],
        ];
    }
}
