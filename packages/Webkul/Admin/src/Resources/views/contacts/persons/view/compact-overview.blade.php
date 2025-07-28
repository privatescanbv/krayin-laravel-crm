{!! view_render_event('admin.contacts.persons.view.compact_overview.before', ['person' => $person]) !!}

<div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
    <x-admin::accordion class="select-none !border-none">
        <x-slot:header class="!p-0">
            <h4 class="font-semibold dark:text-white">
                Persoon Overzicht
            </h4>
        </x-slot>

        <x-slot:content class="mt-4 !px-0 !pb-0">
            <div class="flex flex-col gap-3 text-sm">
        <!-- Name Summary -->
        <div>
            <span class="font-medium text-gray-700 dark:text-gray-300">Naam:</span>
            <div class="mt-1 text-gray-900 dark:text-gray-100">
                @php
                    $nameParts = array_filter([
                        $person->salutation,
                        $person->initials,
                        $person->first_name,
                        $person->lastname_prefix,
                        $person->last_name
                    ]);
                    
                    // Add married name in parentheses if it exists
                    if ($person->married_name) {
                        $marriedNamePart = $person->married_name_prefix 
                            ? "({$person->married_name_prefix} {$person->married_name})"
                            : "({$person->married_name})";
                        $nameParts[] = $marriedNamePart;
                    }
                    
                    $fullName = implode(' ', $nameParts);
                @endphp
                {{ $fullName ?: $person->name }}
            </div>
        </div>

        <!-- Address -->
        <div>
            <span class="font-medium text-gray-700 dark:text-gray-300">Adres:</span>
            <div class="mt-1 text-gray-900 dark:text-gray-100">
                @if($person->address && $person->address->full_address)
                    {{ $person->address->full_address }}
                @else
                    <span class="text-gray-500 dark:text-gray-400 italic">Geen adres</span>
                @endif
            </div>
        </div>

        <!-- Contact Information -->
        <div>
            <span class="font-medium text-gray-700 dark:text-gray-300">Contactgegevens:</span>
            <div class="mt-1">
                <!-- Email Addresses -->
                @if($person->emails && count($person->emails) > 0)
                    @php
                        $defaultEmail = collect($person->emails)->firstWhere('is_default', true) 
                                       ?? collect($person->emails)->first();
                        $otherEmails = collect($person->emails)->reject(function($email) use ($defaultEmail) {
                            return $email['value'] === $defaultEmail['value'];
                        });
                    @endphp
                    
                    <div class="text-gray-900 dark:text-gray-100">
                        {{ $defaultEmail['value'] }}
                    </div>
                    
                    @if($otherEmails->count() > 0)
                        <div class="text-xs text-gray-500 dark:text-gray-400 italic ml-2">
                            @foreach($otherEmails as $email)
                                {{ $email['value'] }}@if(!$loop->last), @endif
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="text-gray-500 dark:text-gray-400 italic">Geen e-mailadres</div>
                @endif

                <!-- Phone Numbers -->
                @if($person->phones && count($person->phones) > 0)
                    @php
                        $defaultPhone = collect($person->phones)->firstWhere('is_default', true) 
                                       ?? collect($person->phones)->first();
                        $otherPhones = collect($person->phones)->reject(function($phone) use ($defaultPhone) {
                            return $phone['value'] === $defaultPhone['value'];
                        });
                    @endphp
                    
                    <div class="text-gray-900 dark:text-gray-100 mt-1">
                        {{ $defaultPhone['value'] }}
                    </div>
                    
                    @if($otherPhones->count() > 0)
                        <div class="text-xs text-gray-500 dark:text-gray-400 italic ml-2">
                            @foreach($otherPhones as $phone)
                                {{ $phone['value'] }}@if(!$loop->last), @endif
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="text-gray-500 dark:text-gray-400 italic mt-1">Geen telefoonnummer</div>
                @endif
            </div>
        </div>
            </div>
        </x-slot>
    </x-admin::accordion>
</div>

{!! view_render_event('admin.contacts.persons.view.compact_overview.after', ['person' => $person]) !!}