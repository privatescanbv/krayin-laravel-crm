<div class="relative rounded-xl border border-indigo-100 bg-indigo-50/80
            dark:border-zinc-700 dark:bg-zinc-800/70
            shadow-sm hover:shadow-md transition p-4">
    <!-- accent links -->
    <span class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-indigo-600 dark:bg-indigo-500"></span>

<dt class="flex items-start gap-3">

<dd class="min-w-0 flex-1">
    <div class="flex items-center gap-2">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
            {{ $lead->name }}
        </h3>

        <!-- status badge (optioneel) -->
        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium
                     bg-white text-indigo-700 border border-indigo-200
                     dark:bg-indigo-900/40 dark:text-indigo-200 dark:border-indigo-700">
          {{ $lead->stage->name ?? 'Geen status' }}
        </span>
    </div>

    <!-- gegevens -->
    <dl class="mt-2 grid grid-cols-3 gap-x-3 gap-y-1 text-[13px]">
        @if($lead->phones && is_array($lead->phones) && count($lead->phones) > 0)
            @php
                $defaultPhone = collect($lead->phones)->firstWhere('is_default', true)
                                 ?? collect($lead->phones)->first();
                $otherPhones = collect($lead->phones)->reject(function($phone) use ($defaultPhone) {
                    return $defaultPhone && isset($defaultPhone['value']) && ($phone['value'] ?? null) === ($defaultPhone['value'] ?? null);
                });
            @endphp
            @if($defaultPhone)
                <dt class="col-span-1 text-gray-500 dark:text-gray-400">Telefoons:</dt>
                <dd class="col-span-2 min-w-0">
                    <a href="tel:{{ $defaultPhone['value'] ?? '' }}"
                       class="block w-full truncate text-gray-900 dark:text-gray-100 hover:underline">
                        <span class="font-semibold">{{ $defaultPhone['value'] ?? '' }}</span>
                        <span class="ml-2 text-xs text-gray-500">(default)</span>
                    </a>
                </dd>
            @endif

            @foreach($otherPhones as $phone)
                <dt class="col-span-1 text-gray-500 dark:text-gray-400">Overige:</dt>
                <dd class="col-span-2 min-w-0">
                    <a href="tel:{{ $phone['value'] ?? '' }}"
                       class="block w-full truncate text-gray-900 dark:text-gray-100 hover:underline">
                        <span class="font-semibold">{{ $phone['value'] ?? '' }}</span>
                    </a>
                </dd>
            @endforeach

        @endif
        <!-- Email Addresses -->
        @if($lead->emails && is_array($lead->emails) && count($lead->emails) > 0)
            @php
                $defaultEmail = collect($lead->emails)->firstWhere('is_default', true)
                               ?? collect($lead->emails)->first();
                $otherEmails = collect($lead->emails)->reject(function($email) use ($defaultEmail) {
                    return $email['value'] === $defaultEmail['value'];
                });
            @endphp
            <dt class="col-span-1 text-gray-500 dark:text-gray-400">E-mails:</dt>
            <dd class="col-span-2 min-w-0">
                {{ $defaultEmail['value'] }} <span class="ml-2 text-xs text-gray-500">(default)</span>
            </dd>

            @if($otherEmails->count() > 0)
                <dt class="col-span-1 text-gray-500 dark:text-gray-400">E-mails:</dt>
                <dd class="col-span-2 min-w-0">
                    @foreach($otherEmails as $email)
                        {{ $email['value'] }}@if(!$loop->last)
                            ,
                        @endif
                    @endforeach
                </dd>
            @endif
        @endif


        <!-- optioneel: e-mail -->
        <!--
<dt class="col-span-1 text-gray-500 dark:text-gray-400">E-mail</dt>
<dd class="col-span-2 min-w-0">
<a href="mailto:bart@example.com" class="block w-full truncate text-gray-900 dark:text-gray-100 hover:underline">
bart@example.com
        </a>
      </dd>
-->
    </dl>

    @if($show_actions ?? true)
        <!-- acties -->
        <div class="mt-3 flex flex-wrap gap-2">
            {{--            <a href="#"--}}
            {{--               class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs--}}
            {{--                  bg-indigo-600 text-white hover:bg-indigo-700">--}}
            {{--                Bel--}}
            {{--            </a>--}}
            <a href="{{ route('admin.leads.view', $lead->id) }}"
               class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs
                  bg-white text-indigo-700 border border-indigo-200 hover:bg-indigo-50
                  dark:bg-transparent dark:text-indigo-200 dark:border-zinc-600">
                Bekijk lead
            </a>
        </div>
@endif
</div>
