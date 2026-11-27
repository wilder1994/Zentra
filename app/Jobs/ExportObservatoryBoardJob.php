<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\Observatory\CompileObservatoryPptx;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\View\View;
use Throwable;

final class ExportObservatoryBoardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 110;

    public function __construct(public readonly string $token) {}

    public function handle(CompileObservatoryPptx $compile): void
    {
        $state = Cache::get(self::key($this->token));
        if (! is_array($state)) {
            return;
        }

        try {
            $user = User::query()->findOrFail((int) $state['user_id']);
            $query = is_array($state['query'] ?? null) ? $state['query'] : [];
            $allowed = $state['allowed'] ?? null;
            $file = ($state['panel'] ?? '') === 'client'
                ? $compile->forClient((int) $state['client_id'], is_array($allowed) ? $allowed : null, $query)
                : $compile->forCompany($user, (int) $state['company_id'], is_array($allowed) ? $allowed : null, $query);
            $state['status'] = 'ready';
            $state['path'] = $file['path'];
            $state['filename'] = $file['filename'];
        } catch (Throwable $e) {
            $state['status'] = 'failed';
            $state['message'] = 'No se pudo armar el informe.';
            report($e);
        }

        Cache::put(self::key($this->token), $state, now()->addHour());
    }

    public static function key(string $token): string
    {
        return 'obs-pptx:'.$token;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function start(Request $request, array $payload, string $statusRoute, string $fileRoute): BinaryFileResponse|View
    {
        $token = (string) Str::uuid();
        $payload['user_id'] = (int) $request->user()->id;
        $payload['status'] = 'queued';
        $payload['query'] = $request->query->all();
        Cache::put(self::key($token), $payload, now()->addHour());
        self::dispatch($token);
        $fresh = Cache::get(self::key($token));
        if (is_array($fresh) && ($fresh['status'] ?? '') === 'ready' && is_string($fresh['path'] ?? null) && is_file($fresh['path'])) {
            return response()->download($fresh['path'], (string) $fresh['filename'])->deleteFileAfterSend();
        }

        return view('modules.observatory.partials.export-wait', [
            'statusUrl' => route($statusRoute, $token),
            'fileUrl' => route($fileRoute, $token),
        ]);
    }
}
