<?php

declare(strict_types=1);

namespace App\Support\Observatory;

/** Topes de evidencia del reporte público y de WhatsApp. La app de supervisores no los usa. */
final class ObservatoryMediaLimits
{
    public const MAX_PHOTOS = 2;

    /** Kilobytes. 10 MB cabe en una foto de celular sin recortar a 4 MB. */
    public const IMAGE_KB = 10240;

    /** Kilobytes. */
    public const VIDEO_KB = 20480;

    public const VIDEO_SECONDS = 30;

    public const MIN_BODY = 1;
}
