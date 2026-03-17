<?php

namespace App\Http\Controllers\Api;

use App\Enums\FormType;
use App\Events\PatientFormCompletedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventWebhookRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;

class EventWebhookController extends Controller
{
    /**
     * Handle an application webhook event.
     *
     * @group Application webhooks
     *
     * @bodyParam entity_type string required Must be `forms`. Example: forms
     * @bodyParam id string required Form ID. Example: form-abc-123
     * @bodyParam action string required Must be `STATUS_UPDATE`. Example: STATUS_UPDATE
     * @bodyParam status string required Form status. Example: completed
     * @bodyParam url string required URL to view the form. Example: https://forms.example.com/form-abc-123
     * @bodyParam person_id integer required CRM Person ID of the patient. Example: 42
     * @bodyParam form_type string required From type. Example 'privatescan','herniapoli';
     *
     * @response 200 {"status":"ok"}
     * @response 422 {"message":"The person_id field is required.","errors":{}}
     */
    public function __invoke(EventWebhookRequest $request): JsonResponse
    {
        Log::info('Application webhook event received', [
            'entity_type' => $request->input('entity_type'),
            'entity_id'   => $request->input('id'),
            'action'      => $request->input('action'),
            'status'      => $request->input('status'),
            'url'         => $request->input('url'),
            'person_id'   => $request->input('person_id'),
            'form_type'   => $request->input('form_type'),
        ]);

        if ($request->input('status') === 'completed' && $request->get('entity_type') === 'forms') {
            /** @var Person $person */
            $person = Person::findOrFail($request->integer('person_id'));
            PatientFormCompletedEvent::dispatch($person, $request->input('id'), FormType::from($request->input('form_type')));
        }

        return response()->json(['status' => 'ok']);
    }
}
