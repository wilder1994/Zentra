<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Observatory\ObservatoryMediaLimits;
use App\Support\Observatory\ObservatoryVideoDuration;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final class ObservatoryVideoRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $seconds = ObservatoryVideoDuration::seconds($value->getPathname());
        if ($seconds === null) {
            $fail('No pude leer la duración del video. Envíe un mp4 de hasta '.ObservatoryMediaLimits::VIDEO_SECONDS.' segundos.');

            return;
        }

        if ($seconds > ObservatoryMediaLimits::VIDEO_SECONDS + 0.4) {
            $fail('El video puede durar hasta '.ObservatoryMediaLimits::VIDEO_SECONDS.' segundos.');
        }
    }
}
