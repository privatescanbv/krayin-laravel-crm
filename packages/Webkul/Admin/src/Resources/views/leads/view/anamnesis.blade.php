<!-- Anamnesis Information -->
<div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
    <div class="flex items-center justify-between">
        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">
            Anamnese
        </h4>

        @if($lead->anamnesis)
            <a
                href="{{ route('admin.anamnesis.edit', $lead->anamnesis->id) }}"
                class="inline-flex items-center gap-x-1 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
            >
                <span class="icon-edit text-sm"></span>
                Bewerken
            </a>
        @endif
    </div>

    @if($lead->anamnesis)
        <div class="grid grid-cols-1 gap-4 text-sm">
            <!-- Basic Info -->
            <div class="space-y-2">
                <div class="mb-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Naam:</div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ $lead->anamnesis->name ?: '-' }}
                    </div>
                </div>

                @if($lead->anamnesis->description)
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Beschrijving:</span>
                        <span class="font-medium dark:text-white">{{ Str::limit($lead->anamnesis->description, 50) }}</span>
                    </div>
                @endif
            </div>

            <!-- Physical Info -->
            @if($lead->anamnesis->height || $lead->anamnesis->weight)
                <div class="space-y-2">
                    <h5 class="font-medium text-gray-800 dark:text-gray-200">Fysieke gegevens</h5>
                    @if($lead->anamnesis->height)
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Lengte:</span>
                            <span class="font-medium dark:text-white">{{ $lead->anamnesis->height }} cm</span>
                        </div>
                    @endif
                    @if($lead->anamnesis->weight)
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Gewicht:</span>
                            <span class="font-medium dark:text-white">{{ $lead->anamnesis->weight }} kg</span>
                        </div>
                    @endif
                    
                    <!-- BMI Calculation and Display -->
                    @if($lead->anamnesis->height && $lead->anamnesis->weight)
                        @php
                            $heightInMeters = $lead->anamnesis->height / 100;
                            $bmi = round($lead->anamnesis->weight / ($heightInMeters * $heightInMeters), 1);
                            
                            // BMI categories and colors
                            if ($bmi < 18.5) {
                                $bmiCategory = 'Ondergewicht';
                                $bmiColor = 'bg-blue-500';
                                $bmiTextColor = 'text-blue-700';
                                $bmiBgColor = 'bg-blue-50';
                            } elseif ($bmi < 25) {
                                $bmiCategory = 'Normaal gewicht';
                                $bmiColor = 'bg-green-500';
                                $bmiTextColor = 'text-green-700';
                                $bmiBgColor = 'bg-green-50';
                            } elseif ($bmi < 30) {
                                $bmiCategory = 'Overgewicht';
                                $bmiColor = 'bg-yellow-500';
                                $bmiTextColor = 'text-yellow-700';
                                $bmiBgColor = 'bg-yellow-50';
                            } else {
                                $bmiCategory = 'Obesitas';
                                $bmiColor = 'bg-red-500';
                                $bmiTextColor = 'text-red-700';
                                $bmiBgColor = 'bg-red-50';
                            }
                            
                            // Calculate position for BMI indicator (BMI scale from 15 to 40)
                            $bmiPosition = min(max(($bmi - 15) / 25 * 100, 0), 100);
                        @endphp
                        
                        <div class="mt-4 p-3 {{ $bmiBgColor }} rounded-lg border">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-600 dark:text-gray-400">BMI:</span>
                                <span class="font-bold {{ $bmiTextColor }}">{{ $bmi }} - {{ $bmiCategory }}</span>
                            </div>
                            
                            <!-- BMI Visual Bar -->
                            <div class="relative">
                                <div class="w-full h-6 bg-gray-200 rounded-full overflow-hidden">
                                    <!-- BMI scale background -->
                                    <div class="h-full flex">
                                        <div class="bg-blue-300 flex-1"></div>      <!-- Underweight -->
                                        <div class="bg-green-300 flex-1"></div>    <!-- Normal -->
                                        <div class="bg-yellow-300 flex-1"></div>   <!-- Overweight -->
                                        <div class="bg-red-300 flex-1"></div>      <!-- Obese -->
                                    </div>
                                </div>
                                
                                <!-- BMI indicator -->
                                <div class="absolute top-0 h-6 w-1 {{ $bmiColor }} rounded-full transform -translate-x-1/2" 
                                     style="left: {{ $bmiPosition }}%;">
                                </div>
                            </div>
                            
                            <!-- BMI scale labels -->
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>15</span>
                                <span>18.5</span>
                                <span>25</span>
                                <span>30</span>
                                <span>40</span>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Medical Conditions -->
            @php
                $conditions = collect([
                    'metals' => 'Metalen',
                    'medications' => 'Medicijnen',
                    'glaucoma' => 'Glaucoom',
                    'claustrophobia' => 'Claustrofobie',
                    'dormicum' => 'Dormicum',
                    'heart_surgery' => 'Hart operatie',
                    'implant' => 'Implantaat',
                    'surgeries' => 'Operaties',
                    'hereditary_heart' => 'Hartafwijking',
                    'hereditary_vascular' => 'Hart-/vaatziekten familie',
                    'hereditary_tumors' => 'Kanker familie',
                    'allergies' => 'Allergieën',
                    'back_problems' => 'Rugklachten',
                    'heart_problems' => 'Hartproblemen',
                    'smoking' => 'Rookt',
                    'diabetes' => 'Diabetes',
                    'digestive_problems' => 'Spijsverteringsklachten',
                    'active' => 'Lichamelijk actief'
                ])->filter(function($label, $field) use ($lead) {
                    return $lead->anamnesis->{$field} == 1;
                });
            @endphp

            @if($conditions->isNotEmpty())
                <div class="space-y-2">
                    <h5 class="font-medium text-gray-800 dark:text-gray-200">Medische condities</h5>
                    <div class="flex flex-wrap gap-1">
                        @foreach($conditions as $field => $label)
                            <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">
                                {{ $label }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Last Updated -->
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                    <span>Laatst bijgewerkt:</span>
                    <span>{{ $lead->anamnesis->updated_at ? $lead->anamnesis->updated_at->format('d-m-Y H:i') : '-' }}</span>
                </div>
            </div>
        </div>
    @else
        <div class="text-center text-gray-500 dark:text-gray-400">
            <p>Geen anamnese gevonden</p>
        </div>
    @endif
</div>
