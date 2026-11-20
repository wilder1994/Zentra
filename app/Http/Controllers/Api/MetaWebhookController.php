<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Meta\ReceiveMetaWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class MetaWebhookController extends Controller
{
    public function __construct(
        private readonly ReceiveMetaWebhookService $webhooks,
    ) {}

    public function verify(Request $request): Response
    {
        $challenge = $this->webhooks->challenge($request);
        if ($challenge === null) {
            return response('Forbidden', 403);
        }

        return response($challenge, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function store(Request $request): Response
    {
        if (! $this->webhooks->signatureIsValid($request)) {
            return response('Forbidden', 403);
        }

        $this->webhooks->store($request->getContent());

        return response('OK', 200);
    }
};
