@props(['anamnesis', 'showCreatedDate' => false])

@if ($anamnesis)
    <div class="p-2 bg-blue-50 border border-blue-200 rounded dark:bg-blue-900/20 dark:border-blue-800">
        <div class="flex items-center justify-between mb-1">
            <h6 class="text-xs font-semibold text-blue-800 dark:text-blue-200">Anamnese</h6>
            <a
                href="{{ route('admin.anamnesis.edit', $anamnesis->id) }}"
                class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400"
                title="Anamnese bewerken"
            >
                <i class="icon-edit"></i>
            </a>
        </div>

        <div class="space-y-1 text-xs">
            @if ($anamnesis->description)
                <div class="text-gray-700 dark:text-gray-300 mb-2">
                    {{ Str::limit($anamnesis->description, 80) }}
                </div>
            @endif

            @if ($anamnesis->height || $anamnesis->weight)
                <div class="flex gap-2 text-gray-600 dark:text-gray-400">
                    @if ($anamnesis->height)
                        <span>{{ $anamnesis->height }}cm</span>
                    @endif
                    @if ($anamnesis->weight)
                        <span>{{ $anamnesis->weight }}kg</span>
                    @endif
                </div>
            @endif

            @php
                $conditions = collect([
                    'metals' => 'Metaal',
                    'medications' => 'Medicatie',
                    'glaucoma' => 'Glaucoom',
                    'claustrophobia' => 'Claustrofobisch',
                    'dormicum' => 'Rustgevend',
                    'heart_surgery' => 'Hartoperatie',
                    'implant' => 'Implantaat',
                    'surgeries' => 'Operaties',
                    'hereditary_heart' => 'Hartafwijking',
                    'hereditary_vascular' => 'Hart-/vaatziekten',
                    'hereditary_tumors' => 'Kanker familie',
                    'allergies' => 'Allergieën',
                    'back_problems' => 'Kan stil liggen',
                    'heart_problems' => 'Hartproblemen',
                    'smoking' => 'Rookt',
                    'diabetes' => 'Diabetes',
                    'digestive_problems' => 'Spijsverteringsklachten',
                    'active' => 'Lichamelijk actief'
                ])->filter(function($label, $field) use ($anamnesis) {
                    return $anamnesis->{$field} == 1;
                });
            @endphp

            @if ($conditions->isNotEmpty())
                <div class="flex flex-wrap gap-1 mt-1">
                    @foreach ($conditions as $field => $label)
                        <span class="inline-flex items-center rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 px-2 py-0.5 text-xs font-medium">
                            {{ $label }}
                        </span>
                    @endforeach
                </div>
            @endif

            <!-- Last Updated -->
            <div class="flex justify-between text-gray-400 dark:text-gray-500 mt-2 pt-1 border-t border-blue-100 dark:border-blue-800">
                <span>Laatst gewijzigd:</span>
                <span>{{ $anamnesis->updated_at ? $anamnesis->updated_at->format('d-m-Y H:i') : '-' }}</span>
            </div>
        </div>
    </div>
@endif