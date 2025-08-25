@props(['anamnesis'])

<div class="w-full p-4">
    <h4 class="font-semibold dark:text-white mb-2">
        Gekoppelde anamneses
    </h4>
    @if($anamnesis && $anamnesis->count())
        <div class="flex flex-col gap-3">
            @foreach($anamnesis as $anamnesisItem)
                <x-admin::anamnesis.card 
                    :anamnesis="$anamnesisItem" 
                    :show-created-date="true" 
                />
            @endforeach
        </div>
    @else
        <span class="text-gray-500">Geen anamnesis gevonden</span>
    @endif
</div>