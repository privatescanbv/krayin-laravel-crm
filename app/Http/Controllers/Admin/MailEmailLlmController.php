<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\RunEmailLlmExtractionRequest;
use App\Services\Mail\EmailLlmLinkingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Resources\EmailResource;
use Webkul\Email\Repositories\EmailRepository;

class MailEmailLlmController extends Controller
{
    public function __construct(
        private readonly EmailRepository $emailRepository,
        private readonly EmailLlmLinkingService $emailLlmLinkingService,
    ) {}

    public function runSenderExtraction(RunEmailLlmExtractionRequest $request, int $id): JsonResponse
    {
        $email = $this->emailRepository->findOrFail($id);

        $applyLinks = $request->boolean('apply_links');
        $forceRefresh = $request->boolean('force_refresh');

        $result = $this->emailLlmLinkingService->extractAndLink(
            email: $email,
            systemPrompt: $request->validated('system_prompt'),
            applyLinks: $applyLinks,
            trigger: 'manual',
            forceRefresh: $forceRefresh,
        );

        $email = $this->emailRepository
            ->with(['person', 'lead', 'salesLead', 'clinic', 'order'])
            ->findOrFail($id);

        $result['suggestions'] = $this->emailLlmLinkingService->suggestionsAfterExtraction(
            $email,
            $result['links'] ?? [],
        );

        $fromCache = (bool) ($result['from_cache'] ?? false);

        return response()->json([
            'message' => $this->resultMessage($result['status'], $fromCache),
            'result'  => $result,
            'email'   => new EmailResource($email),
        ]);
    }

    public function applySuggestion(Request $request, int $id): JsonResponse
    {
        $email = $this->emailRepository->findOrFail($id);

        $validated = $request->validate(['links' => 'required|array']);

        $this->emailRepository->update($validated['links'], $email->id);

        $this->emailRepository->moveToProcessedIfInbox($email->id);

        $email = $this->emailRepository
            ->with(['person', 'lead', 'salesLead', 'clinic', 'order'])
            ->findOrFail($id);

        return response()->json([
            'message' => 'E-mail gekoppeld.',
            'email'   => new EmailResource($email),
        ]);
    }

    private function resultMessage(string $status, bool $fromCache = false): string
    {
        if ($fromCache) {
            return match ($status) {
                'linked'     => 'Eerdere AI-analyse geladen (e-mail was gekoppeld).',
                'matched'    => 'Eerdere AI-analyse geladen.',
                'no_match'   => 'Eerdere analyse: geen CRM-match voor de afzenders.',
                'no_senders' => 'Eerdere analyse: geen afzenders gevonden.',
                default      => 'Eerdere AI-analyse geladen.',
            };
        }

        return match ($status) {
            'linked'     => 'AI-analyse voltooid en e-mail gekoppeld.',
            'matched'    => 'Suggesties gevonden.',
            'no_match'   => 'Geen CRM-match gevonden voor de gedetecteerde afzenders.',
            'no_senders' => 'Geen afzenders gevonden in de e-mail.',
            default      => 'AI-analyse mislukt.',
        };
    }
}
