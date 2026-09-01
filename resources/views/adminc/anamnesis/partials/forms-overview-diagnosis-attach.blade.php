@props([
    'duplicateMessage' => null,
])
<form method="POST" action="{{ route('admin.anamnesis.diagnosis-form.attach') }}" class="inline">
    @csrf
    <input type="hidden" name="sales_id" value="{{ $salesLead->id }}">
    <input type="hidden" name="person_id" value="{{ $person->id }}">
    <input type="hidden" name="form_type" value="{{ $formType->value }}">
    <input type="hidden" name="return_url" value="{{ $returnUrl }}">
    <input type="hidden" name="force" value="0">
    <button type="submit"
            @disabled(! $personHasPortalAccount)
            class="text-xs text-blue-600 hover:underline disabled:cursor-not-allowed disabled:opacity-50"
            @if ($duplicateMessage)
                onclick="if (!confirm(@json($duplicateMessage))) { return false; } this.form.querySelector('[name=force]').value='1'; return true;"
            @else
                onclick="return confirm('Er wordt een {{ $formType->label() }} klaargezet in het patiëntenportaal voor deze patiënt.')"
            @endif>
        Klaarzetten
    </button>
</form>
