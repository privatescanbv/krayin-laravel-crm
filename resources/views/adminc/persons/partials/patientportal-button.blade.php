@props([
    'person',
    'presentLarge' => false,
    'returnUrl' => null,
])

@if (empty($person->keycloak_user_id))
    <form
        method="POST"
        action="{{ route('admin.contacts.persons.portal.create', $person->id) }}"
        onsubmit="return confirm('Portal account aanmaken voor {{ $person->name }}?')"
        style="display: inline;">
        @csrf
        @if ($returnUrl)
            <input type="hidden" name="return_url" value="{{ $returnUrl }}">
        @endif
        @if($presentLarge)
            <button
                type="submit"
                title="Patiëntportaal account aanmaken"
                class="group flex h-[74px] w-[84px] flex-col items-center justify-center gap-1 rounded-lg border border-transparent bg-activity-note-bg font-medium text-activity-note-text transition-all hover:border-activity-note-border hover:text-blue-700"
            >
                <span class="icon-user text-2xl text-activity-note-text transition-all group-hover:text-blue-700 dark:!text-activity-note-text"></span>
                Portaal
            </button>
        @else
            <button type="submit" class="icon-user rounded-md p-1.5 text-xl transition-all hover:bg-neutral-bg dark:hover:bg-gray-950 text-activity-note-text hover:text-blue-700" title="Patiëntportaal account aanmaken"></button>
        @endif
    </form>
@else
    <form
        method="POST"
        action="{{ route('admin.contacts.persons.portal.delete', $person->id) }}"
        onsubmit="return confirm('Portal account verwijderen voor {{ $person->name }}?')"
        style="display: inline;">
        @csrf
        @method('DELETE')
        @if ($returnUrl)
            <input type="hidden" name="return_url" value="{{ $returnUrl }}">
        @endif
        @if($presentLarge)
            <button
                type="submit"
                title="Patiëntportaal account intrekken"
                class="group flex h-[74px] w-[84px] flex-col items-center justify-center gap-1 rounded-lg border border-red-100 bg-red-50 font-medium text-status-expired-text transition-all hover:border-error hover:bg-red-100 hover:text-red-700 dark:border-red-700 dark:bg-red-950 dark:text-red-300 dark:hover:bg-red-950"
            >
                <span class="icon-cross-large text-2xl text-status-expired-text transition-all group-hover:text-red-700 dark:!text-red-300"></span>
                Portaal
            </button>
        @else
            <button type="submit" class="icon-cross-large rounded-md p-1.5 text-xl transition-all hover:bg-neutral-bg dark:hover:bg-gray-950 text-status-expired-text hover:text-red-700" title="Patiëntportaal account intrekken"></button>
        @endif
    </form>
@endif
