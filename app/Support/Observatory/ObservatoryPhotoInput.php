<?php

declare(strict_types=1);

namespace App\Support\Observatory;

use App\Rules\ObservatoryVideoRule;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class ObservatoryPhotoInput
{
    /** @return list<UploadedFile> */
    public static function files(Request $request, int $max = 3): array
    {
        $photos = $request->file('photos', []);
        if ($photos instanceof UploadedFile) {
            $photos = [$photos];
        }
        if (! is_array($photos)) {
            $photos = [];
        }

        $out = [];
        foreach ($photos as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $out[] = $file;
            }
        }

        return array_slice($out, 0, $max);
    }

    public static function video(Request $request): ?UploadedFile
    {
        $file = $request->file('video');

        return $file instanceof UploadedFile && $file->isValid() ? $file : null;
    }

    /** @return array<string, mixed> */
    public static function optionalRules(): array
    {
        return [
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ];
    }

    /** @return array<string, mixed> */
    public static function linkRules(): array
    {
        return [
            'photos' => ['nullable', 'array', 'max:'.ObservatoryMediaLimits::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:'.ObservatoryMediaLimits::IMAGE_KB],
            'video' => [
                'nullable',
                'file',
                'mimetypes:video/mp4,video/webm,video/quicktime',
                'max:'.ObservatoryMediaLimits::VIDEO_KB,
                new ObservatoryVideoRule,
            ],
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'photos' => 'fotos',
            'photos.*' => 'foto',
            'photo' => 'foto',
            'video' => 'video',
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'photos.max' => 'Puede adjuntar hasta tres fotos.',
        ];
    }

    /** @return array<string, string> */
    public static function linkMessages(): array
    {
        return [
            'photos.max' => 'Puede adjuntar hasta '.ObservatoryMediaLimits::MAX_PHOTOS.' fotos.',
            'photos.*.max' => 'Cada foto puede pesar hasta 10 MB.',
            'video.max' => 'El video puede pesar hasta 20 MB.',
        ];
    }
}
