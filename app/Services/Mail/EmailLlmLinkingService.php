<?php

namespace App\Services\Mail;

use App\Models\Clinic;
use App\Models\Order;
use App\Models\SalesLead;
use App\Services\Ai\LlmJsonParseException;
use App\Services\Ai\LlmService;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\Lead\Models\Lead;

/**
 * Currently not used; applyLinks
 */
class EmailLlmLinkingService
{
    public function __construct(
        private readonly LlmService $llmService,
        private readonly EmailEntityLinker $emailEntityLinker,
        private readonly EmailRepository $emailRepository,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     senders: list<array{email: string, name: string, confidence: float, role: string}>,
     *     links: array<string, int>,
     *     request_payload: array<string, mixed>,
     *     llm_response: array<string, mixed>|null,
     *     duration_ms: int,
     *     error: string|null,
     *     metadata: array<string, mixed>
     * }
     */
    public function extractAndLink(
        Email $email,
        ?string $systemPrompt = null,
        bool $applyLinks = true,
        string $trigger = 'automatic',
        bool $forceRefresh = false,
    ): array {
        if ($trigger === 'manual' && ! $forceRefresh && $this->hasUsableCachedExtraction($email)) {
            return $this->resultFromCachedMetadata($email);
        }

        $startedAt = microtime(true);
        $wasAlreadyLinked = $email->has_relationships;
        $payload = $this->buildRequestPayload($email);
        $logContext = ['email_id' => $email->id, 'trigger' => $trigger];

        $metadata = [
            'last_run_at'     => now()->toIso8601String(),
            'trigger'         => $trigger,
            'system_prompt'   => $systemPrompt ?: config('ai_prompts.email_sender_extraction'),
            'request_payload' => $payload,
            'status'          => 'error',
            'senders'         => [],
            'links'           => [],
            'llm_response'    => null,
            'llm_raw_content' => null,
            'duration_ms'     => 0,
            'error'           => null,
        ];

        try {
            $response = $this->llmService->chatJson(
                'email_sender_extraction',
                json_encode($payload, JSON_THROW_ON_ERROR),
                $logContext,
                $systemPrompt,
            );

            $metadata['llm_response'] = $response;
            $senders = $this->normalizeSenders($response['senders'] ?? []);
            $metadata['senders'] = $senders;
            $metadata['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);

            if ($senders === []) {
                $metadata['status'] = 'no_senders';
                $this->persistMetadata($email, $metadata);

                return $this->buildResult($metadata);
            }

            $linkData = $this->resolveLinks($senders);
            $metadata['links'] = $linkData;

            if ($linkData === []) {
                $metadata['status'] = 'no_match';
                $this->persistMetadata($email, $metadata);

                return $this->buildResult($metadata);
            }

            if ($applyLinks && ! $wasAlreadyLinked) {
                $this->emailRepository->update($linkData, $email->id);
                $email->refresh();
                $metadata['status'] = 'linked';
            } else {
                $metadata['status'] = 'matched';
            }

            $this->persistMetadata($email, $metadata);

            return $this->buildResult($metadata);
        } catch (LlmJsonParseException $exception) {
            $metadata['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
            $metadata['error'] = $exception->getMessage();
            $metadata['llm_raw_content'] = $exception->rawContent;
            $metadata['llm_extracted_json'] = $exception->extractedJson;
            $metadata['status'] = 'error';
            $this->persistMetadata($email, $metadata);
            Log::error('Could not perform LLM action to find e-mail addresses from email: '.$exception->getMessage());

            return $this->buildResult($metadata);
        } catch (Throwable $exception) {
            $metadata['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
            $metadata['error'] = $exception->getMessage();
            $metadata['status'] = 'error';
            $this->persistMetadata($email, $metadata);
            Log::error('Could not perform LLM action to find e-mail addresses from email: '.$exception->getMessage());

            return $this->buildResult($metadata);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildRequestPayload(Email $email): array
    {
        return [
            'from'     => $email->from,
            'subject'  => $email->subject,
            'reply_to' => $email->reply_to,
            'cc'       => $email->cc,
            'body'     => $this->bodyForLlm($email),
        ];
    }

    public function hasUsableCachedExtraction(Email $email): bool
    {
        if (! is_array($email->llm_metadata)) {
            return false;
        }

        $status = (string) ($email->llm_metadata['status'] ?? '');

        return in_array($status, ['matched', 'linked', 'no_match', 'no_senders'], true);
    }

    /**
     * @return array{
     *     status: string,
     *     senders: list<array{email: string, name: string, confidence: float, role: string}>,
     *     links: array<string, int>,
     *     request_payload: array<string, mixed>,
     *     llm_response: array<string, mixed>|null,
     *     duration_ms: int,
     *     error: string|null,
     *     metadata: array<string, mixed>,
     *     from_cache: bool
     * }
     */
    public function resultFromCachedMetadata(Email $email): array
    {
        $metadata = $email->llm_metadata;

        return array_merge($this->buildResult($metadata), ['from_cache' => true]);
    }

    /**
     * Suggestions for the mail action panel (metadata matches, or active orders when linked to sales).
     *
     * @return list<array{type: string, label: string, links: array<string, int>}>
     */
    public function suggestionsForEmailView(Email $email): array
    {
        $storedLinks = is_array($email->llm_metadata) ? ($email->llm_metadata['links'] ?? []) : [];

        if ($email->sales_lead_id && $email->has_relationships) {
            return $this->activeOrderSuggestionsForSalesLead(
                (int) $email->sales_lead_id,
                $email->order_id ? (int) $email->order_id : null,
            );
        }

        if ($storedLinks !== []) {
            return $this->enrichWithSuggestions($storedLinks);
        }

        if ($email->sales_lead_id) {
            return $this->enrichWithSuggestions(['sales_lead_id' => (int) $email->sales_lead_id]);
        }

        return [];
    }

    /**
     * Suggestions to return after a manual AI run (keeps order hints when linked to sales but LLM found no match).
     *
     * @param  array<string, int>  $links
     * @return list<array{type: string, label: string, links: array<string, int>}>
     */
    public function suggestionsAfterExtraction(Email $email, array $links): array
    {
        if ($links !== []) {
            return $this->enrichWithSuggestions($links);
        }

        return $this->suggestionsForEmailView($email);
    }

    /**
     * Active (non-won/lost) orders for a sales lead — never auto-linked; employee chooses explicitly.
     *
     * @return list<array{type: string, label: string, links: array<string, int>}>
     */
    public function activeOrderSuggestionsForSalesLead(int $salesLeadId, ?int $excludeOrderId = null): array
    {
        $salesLead = SalesLead::with(['orders.stage'])->find($salesLeadId);

        if (! $salesLead) {
            return [];
        }

        $suggestions = [];

        foreach ($salesLead->orders as $order) {
            if ($excludeOrderId !== null && (int) $order->id === $excludeOrderId) {
                continue;
            }

            if ($order->stage?->is_won || $order->stage?->is_lost) {
                continue;
            }

            $suggestions[] = [
                'type'  => 'order',
                'label' => 'Order: '.($order->title ?: $order->order_number ?: '#'.$order->id),
                'links' => [
                    'order_id'      => $order->id,
                    'sales_lead_id' => $salesLeadId,
                ],
            ];
        }

        return $suggestions;
    }

    /**
     * @param  array<string, int>  $links
     * @return list<array{type: string, label: string, links: array<string, int>}>
     */
    public function enrichWithSuggestions(array $links): array
    {
        $suggestions = [];

        if (! empty($links['sales_lead_id'])) {
            $salesLeadId = (int) $links['sales_lead_id'];
            $salesLead = SalesLead::find($salesLeadId);

            $suggestions[] = [
                'type'  => 'sales',
                'label' => 'Sales: '.($salesLead?->name ?? '#'.$salesLeadId),
                'links' => ['sales_lead_id' => $salesLeadId],
            ];

            $suggestions = array_merge(
                $suggestions,
                $this->activeOrderSuggestionsForSalesLead($salesLeadId),
            );
        } elseif (! empty($links['order_id'])) {
            $order = Order::with('salesLead')->find($links['order_id']);
            if ($order?->salesLead) {
                $salesLeadId = $order->salesLead->id;
                $suggestions[] = [
                    'type'  => 'sales',
                    'label' => 'Sales: '.($order->salesLead->name ?? '#'.$salesLeadId),
                    'links' => ['sales_lead_id' => $salesLeadId],
                ];
                $suggestions = array_merge(
                    $suggestions,
                    $this->activeOrderSuggestionsForSalesLead($salesLeadId),
                );
            } else {
                $suggestions[] = [
                    'type'  => 'order',
                    'label' => 'Order: '.($order?->title ?: $order?->order_number ?: '#'.$links['order_id']),
                    'links' => ['order_id' => $links['order_id']],
                ];
            }
        } elseif (! empty($links['lead_id'])) {
            $lead = Lead::find($links['lead_id']);
            $entry = ['lead_id' => $links['lead_id']];
            if (! empty($links['person_id'])) {
                $entry['person_id'] = $links['person_id'];
            }
            $suggestions[] = [
                'type'  => 'lead',
                'label' => 'Lead: '.($lead?->name ?? '#'.$links['lead_id']),
                'links' => $entry,
            ];
        } elseif (! empty($links['person_id'])) {
            $person = Person::find($links['person_id']);
            $suggestions[] = [
                'type'  => 'person',
                'label' => 'Persoon: '.($person?->name ?? '#'.$links['person_id']),
                'links' => ['person_id' => $links['person_id']],
            ];
        }

        if (! empty($links['clinic_id'])) {
            $clinic = Clinic::find($links['clinic_id']);
            $suggestions[] = [
                'type'  => 'clinic',
                'label' => 'Kliniek: '.($clinic?->name ?? '#'.$links['clinic_id']),
                'links' => ['clinic_id' => $links['clinic_id']],
            ];
        }

        return $suggestions;
    }

    /**
     * @param  list<array{email: string, name: string, confidence: float, role: string}>  $senders
     * @return array<string, int>
     */
    public function resolveLinks(array $senders): array
    {
        $linkData = [];
        $linkedAddresses = [];

        foreach ($senders as $sender) {
            $address = $sender['email'];

            if (in_array($address, $linkedAddresses, true)) {
                continue;
            }

            $linkedAddresses[] = $address;
            $linkData = $this->emailEntityLinker->link($linkData, $address);
        }

        return $linkData;
    }

    /**
     * @param  list<mixed>  $senders
     * @return list<array{email: string, name: string, confidence: float, role: string}>
     */
    public function normalizeSenders(array $senders): array
    {
        $normalized = [];

        foreach ($senders as $sender) {
            if (! is_array($sender)) {
                continue;
            }

            $email = strtolower(trim((string) ($sender['email'] ?? '')));

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $normalized[] = [
                'email'      => $email,
                'name'       => trim((string) ($sender['name'] ?? '')),
                'confidence' => (float) ($sender['confidence'] ?? 0),
                'role'       => (string) ($sender['role'] ?? 'other'),
            ];
        }

        usort($normalized, function (array $left, array $right): int {
            $leftOriginal = $left['role'] === 'original_sender' ? 1 : 0;
            $rightOriginal = $right['role'] === 'original_sender' ? 1 : 0;

            if ($leftOriginal !== $rightOriginal) {
                return $rightOriginal <=> $leftOriginal;
            }

            return $right['confidence'] <=> $left['confidence'];
        });

        return $normalized;
    }

    private function bodyForLlm(Email $email): string
    {
        $body = html_entity_decode(strip_tags((string) ($email->reply ?? '')));
        $body = trim(preg_replace('/\s+/u', ' ', $body) ?? '');

        $maxChars = (int) config('services.llm.email_linking.body_max_chars', 6000);

        foreach (['Doorgestuurd bericht', '-----Original Message-----', 'Begin forwarded message', 'Van:', 'From:'] as $needle) {
            $position = stripos($body, $needle);

            if ($position !== false) {
                $start = max(0, $position - 200);
                $snippet = mb_substr($body, $start, $maxChars);

                if (mb_strlen($snippet) >= 80) {
                    return $snippet;
                }
            }
        }

        return mb_substr($body, 0, $maxChars);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function persistMetadata(Email $email, array $metadata): void
    {
        $this->emailRepository->update([
            'llm_metadata' => $metadata,
        ], $email->id);

        $email->llm_metadata = $metadata;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *     status: string,
     *     senders: list<array{email: string, name: string, confidence: float, role: string}>,
     *     links: array<string, int>,
     *     request_payload: array<string, mixed>,
     *     llm_response: array<string, mixed>|null,
     *     duration_ms: int,
     *     error: string|null,
     *     metadata: array<string, mixed>
     * }
     */
    private function buildResult(array $metadata): array
    {
        return [
            'status'          => (string) ($metadata['status'] ?? 'error'),
            'senders'         => $metadata['senders'] ?? [],
            'links'           => $metadata['links'] ?? [],
            'request_payload' => $metadata['request_payload'] ?? [],
            'llm_response'    => $metadata['llm_response'] ?? null,
            'llm_raw_content' => $metadata['llm_raw_content'] ?? null,
            'duration_ms'     => (int) ($metadata['duration_ms'] ?? 0),
            'error'           => $metadata['error'] ?? null,
            'metadata'        => $metadata,
        ];
    }
}
