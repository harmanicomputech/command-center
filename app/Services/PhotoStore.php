<?php

namespace App\Services;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Photos from the field, on the private disk (never in the web root). The
 * phone already shrinks them; the server re-encodes to JPEG (which also
 * drops the location and camera metadata), caps the long side and makes a
 * thumbnail. Storing is idempotent by the photo's UUID.
 */
class PhotoStore
{
    public function store(UploadedFile $file, Model $owner, string $uuid, ?User $user): Photo
    {
        if ($existing = Photo::query()->where('uuid', $uuid)->first()) {
            return $existing;
        }

        $info = @getimagesize($file->getRealPath());
        $image = match ($info[2] ?? null) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
            IMAGETYPE_PNG => @imagecreatefrompng($file->getRealPath()),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file->getRealPath()),
            default => false,
        };

        if (! $image) {
            throw ValidationException::withMessages(['photo' => 'That file isn’t a photo the app can read (JPEG, PNG or WebP).']);
        }

        $image = $this->orient($image, $file->getRealPath(), $info[2]);
        $dir = 'photos/'.now()->format('Y/m');
        $main = $this->resized($image, (int) config('field.photo_max_side'));
        $thumb = $this->resized($image, (int) config('field.photo_thumb_side'));

        $path = "{$dir}/{$uuid}.jpg";
        $thumbPath = "{$dir}/{$uuid}-thumb.jpg";
        Storage::disk('local')->put($path, $this->jpeg($main, 80));
        Storage::disk('local')->put($thumbPath, $this->jpeg($thumb, 72));

        return Photo::create([
            'uuid' => $uuid,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'path' => $path,
            'thumb_path' => $thumbPath,
            'width' => imagesx($main),
            'height' => imagesy($main),
            'bytes' => Storage::disk('local')->size($path),
            'uploaded_by' => $user?->id,
        ]);
    }

    public function delete(Photo $photo): void
    {
        Storage::disk('local')->delete([$photo->path, $photo->thumb_path]);
        $photo->delete();
    }

    private function orient(\GdImage $image, string $path, int $type): \GdImage
    {
        $orientation = $type === IMAGETYPE_JPEG && function_exists('exif_read_data') ? (@exif_read_data($path)['Orientation'] ?? 1) : 1;

        return match ((int) $orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    private function resized(\GdImage $image, int $maxSide): \GdImage
    {
        [$width, $height] = [imagesx($image), imagesy($image)];
        $scale = min(1, $maxSide / max($width, $height));

        if ($scale >= 1) {
            return $image;
        }

        $copy = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagecopyresampled($copy, $image, 0, 0, 0, 0, imagesx($copy), imagesy($copy), $width, $height);

        return $copy;
    }

    private function jpeg(\GdImage $image, int $quality): string
    {
        ob_start();
        imagejpeg($image, null, $quality);

        return (string) ob_get_clean();
    }
}
