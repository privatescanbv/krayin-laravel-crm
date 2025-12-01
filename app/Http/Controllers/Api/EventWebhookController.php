<?php

namespace App\Http\Controllers\Api;

use App\Actions\Forms\FormUpdatedAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventWebhookRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class EventWebhookController extends Controller
{
    public function __construct(private readonly FormUpdatedAction $formAction) {}

    public function __invoke(EventWebhookRequest $request): JsonResponse
    {
        Log::info('Application webhook event received', [
            'entity_type' => $request->input('entity_type'),
            'entity_id'   => $request->input('id'),
            'action'      => $request->input('action'),
            'url'         => $request->input('url'),
            'payload'     => $request->all(),
        ]);

        $this->formAction->execute($request->input('id'), $request->input('status'), $request->input('url'));

        return response()->json(['status' => 'ok']);
    }
}
