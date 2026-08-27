@props([
    'salesLead',
    'person',
    'returnUrl' => null,
])
@php
    use App\Enums\FormType;
    use App\Models\Anamnesis;
    use App\Support\GvlFormLink;

    $anamnesis = Anamnesis::query()
        ->where('sales_id', $salesLead->id)
        ->where('person_id', $person->id)
        ->with('gvlForms')
        ->first();

    $personHasPortalAccount = ! empty($person->keycloak_user_id);

    $formsByType = $anamnesis
        ? $anamnesis->gvlForms->keyBy(fn ($f) => $f->gvl_form_type?->value)
        : collect();

    $statusLabels = [
        'new'       => ['Nieuw', 'text-gray-600'],
        'step1'     => ['Stap 1', 'text-yellow-600'],
        'step2'     => ['Stap 2', 'text-activity-note-text'],
        'step3'     => ['Stap 3', 'text-status-active-text'],
        'completed' => ['Voltooid', 'text-status-active-text'],
    ];

    $returnUrl = $returnUrl ?: strtok(url()->current(), '#') . '#anamnese';
@endphp

<div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    <h4 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Diagnose formulieren</h4>

    @unless ($personHasPortalAccount)
        <p class="mb-2 text-xs text-amber-600 dark:text-amber-500">
            <span class="icon-warning"></span>
            Maak eerst een patiëntportaal account aan voor deze patiënt om een diagnoseformulier klaar te zetten.
        </p>
    @endunless

    <div class="space-y-1.5">
        @foreach (FormType::diagnosisCases() as $type)
            @php
                $form = $formsByType->get($type->value);
                $statusValue = $form?->gvl_form_status?->value ?? 'new';
                [$statusText, $statusColor] = $statusLabels[$statusValue] ?? ['Onbekend', 'text-gray-400'];
                $openUrl = $form
                    ? GvlFormLink::adminOpenUrl($form->gvl_form_link, (int) $person->id, $personHasPortalAccount)
                    : null;
            @endphp
            <div class="flex items-center gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800">
                <span class="w-44 shrink-0 font-medium text-gray-700 dark:text-gray-200">{{ $type->label() }}</span>

                @if ($form)
                    <span class="text-gray-300 dark:text-gray-600">|</span>
                    <span class="{{ $statusColor }} shrink-0 font-medium">{{ $statusText }}</span>

                    @if ($openUrl)
                        <span class="text-gray-300 dark:text-gray-600">|</span>
                        <a href="{{ $openUrl }}" target="_blank" class="text-activity-note-text hover:underline">
                            Formulier bekijken
                        </a>
                    @endif

                    <form method="POST" action="{{ route('admin.anamnesis.diagnosis-form.detach') }}" class="ml-auto inline">
                        @csrf
                        <input type="hidden" name="gvl_form_record_id" value="{{ $form->id }}">
                        <input type="hidden" name="return_url" value="{{ $returnUrl }}">
                        <button type="submit"
                                class="rounded px-2 py-1 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                                onclick="return confirm('Weet je zeker dat je dit diagnoseformulier wil ontkoppelen?')">
                            Ontkoppel
                        </button>
                    </form>
                @else
                    <span class="text-gray-300 dark:text-gray-600">|</span>
                    <span class="shrink-0 text-gray-400 dark:text-gray-500">Nog niet klaargezet</span>

                    <form method="POST" action="{{ route('admin.anamnesis.diagnosis-form.attach') }}" class="ml-auto inline">
                        @csrf
                        <input type="hidden" name="sales_id" value="{{ $salesLead->id }}">
                        <input type="hidden" name="person_id" value="{{ $person->id }}">
                        <input type="hidden" name="form_type" value="{{ $type->value }}">
                        <input type="hidden" name="return_url" value="{{ $returnUrl }}">
                        <button type="submit"
                                @disabled(! $personHasPortalAccount)
                                class="primary-button text-xs disabled:cursor-not-allowed disabled:opacity-50"
                                onclick="return confirm('Er wordt een {{ $type->label() }} klaargezet in het patiëntenportaal voor deze patiënt.')">
                            Klaarzetten
                        </button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</div>
