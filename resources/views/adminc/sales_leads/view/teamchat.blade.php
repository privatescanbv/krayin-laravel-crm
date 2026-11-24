@php
    $messages = $salesLead->teamChatMessages()->with('user')->orderBy('created_at')->orderBy('id')->get();
@endphp

<div class="flex h-[600px] w-full flex-1 flex-col rounded-lg border bg-gray-100 dark:border-gray-800 dark:bg-gray-950">
    <div class="border-b border-gray-200 px-4 py-2 text-xs text-gray-500 dark:border-gray-800">
        Intern gesprek met de arts over deze sales. Niet zichtbaar voor de patiënt.
    </div>

    <div class="flex-1 space-y-4 overflow-y-auto p-4" id="team-chat-container">
        @forelse ($messages as $message)
            @php $mine = $message->created_by === auth()->id(); @endphp
            <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                <div class="flex max-w-[70%] flex-col">
                    <div class="mb-1 text-xs text-gray-500 {{ $mine ? 'text-right' : '' }}">
                        {{ $message->user?->name ?? 'Onbekend' }} - {{ $message->created_at->format('d-m-Y H:i') }}
                    </div>
                    <div class="rounded-lg px-4 py-2 shadow-sm {{ $mine ? 'bg-green-100 dark:bg-green-900' : 'bg-white dark:bg-gray-800' }} text-gray-800 dark:text-gray-200">
                        {!! nl2br(e(\App\Services\PlainText::fromHtml($message->comment) ?? '')) !!}
                    </div>
                </div>
            </div>
        @empty
            <div class="py-10 text-center text-gray-500">Nog geen berichten.</div>
        @endforelse
    </div>

    <div class="rounded-b-lg border-t border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <form action="{{ route('admin.sales-leads.team-chat.store', $salesLead->id) }}" method="POST">
            @csrf
            <div class="flex gap-2">
                <textarea
                    name="text"
                    class="flex-1 rounded-md border border-gray-300 p-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    rows="2"
                    maxlength="2000"
                    placeholder="Typ een bericht..."
                    required
                ></textarea>
                <button type="submit" class="self-end rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Verstuur</button>
            </div>
        </form>
    </div>
</div>
