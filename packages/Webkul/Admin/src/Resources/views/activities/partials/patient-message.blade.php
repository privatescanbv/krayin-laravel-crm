@php
    use App\Enums\ActivityType;
    use App\Enums\PatientMessageSenderType;
    // Get all patient messages linked to this person, sorted by created_at
    // We assume the activity has a person attached or lead->person
    $person = null;
    if($activity->persons->isNotEmpty()) {
        $person = $activity->persons->first();
    } elseif($activity->lead && $activity->lead->person) {
        $person = $activity->lead->person;
    }

    $messages = collect();
    if($person) {
        $messages = $person->patientMessages()->orderBy('created_at')->get();
    }
@endphp

<div class="flex w-full flex-1 flex-col h-[600px] rounded-lg border bg-gray-100 dark:bg-gray-950 dark:border-gray-800">
    <!-- Chat Area -->
    <div class="flex-1 overflow-y-auto p-4 space-y-4" id="chat-container">
        @if($messages->isEmpty())
            <div class="text-center text-gray-500 py-10">
                Geen berichten gevonden voor deze persoon.
            </div>
        @else
            @foreach($messages as $message)
                @php
                    $isEmployee = $message->sender_type === PatientMessageSenderType::STAFF || $message->sender_type === PatientMessageSenderType::SYSTEM;
                    // Employee Left, Patient Right (as per requirements)
                    $alignment = $isEmployee ? 'justify-start' : 'justify-end';
                    $bubbleColor = $isEmployee ? 'bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200' : 'bg-green-100 dark:bg-green-900 text-gray-800 dark:text-gray-200';
                    $senderName = $isEmployee ? ($message->sender->name ?? 'Medewerker') : 'Patiënt';
                @endphp

                <div class="flex {{ $alignment }}">
                    <div class="flex flex-col max-w-[70%]">
                        <div class="text-xs text-gray-500 mb-1 flex items-center gap-2 {{ $isEmployee ? 'flex-row' : 'flex-row-reverse' }}">
                            <span>{{ $senderName }} - {{ $message->created_at->format('d-m-Y H:i') }}</span>
                            <span class="w-2 h-2 rounded-full {{ $message->is_read ? 'bg-blue-500' : 'bg-gray-300' }}" title="{{ $message->is_read ? 'Gelezen' : 'Niet gelezen' }}"></span>
                        </div>
                        <div class="px-4 py-2 rounded-lg shadow-sm {{ $bubbleColor }} {{ $isEmployee ? 'rounded-tl-none' : 'rounded-tr-none' }}">
                            {!! nl2br(e($message->body)) !!}
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    <!-- Input Area -->
    <div class="p-4 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 rounded-b-lg">
        <form action="{{ route('admin.activities.store') }}" method="POST">
            @csrf
            <input type="hidden" name="type" value="{{ ActivityType::PATIENT_MESSAGE->value }}">
            <!-- We send lead_id if available, logic in observer handles creation of PatientMessage -->
            @if($activity->lead_id)
                <input type="hidden" name="lead_id" value="{{ $activity->lead_id }}">
            @endif
            <input type="hidden" name="is_done" value="1">
            <input type="hidden" name="title" value="Bericht vanuit CRM">

            <div class="flex gap-2">
                <textarea
                    name="comment"
                    class="flex-1 rounded-md border border-gray-300 p-2 focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300"
                    rows="2"
                    placeholder="Typ een bericht..."
                    required
                ></textarea>
                <button type="submit" class="self-end px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Verstuur
                </button>
            </div>
        </form>
    </div>
</div>

@pushOnce('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chatContainer = document.getElementById('chat-container');
            if (chatContainer) {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
        });
    </script>
@endPushOnce

