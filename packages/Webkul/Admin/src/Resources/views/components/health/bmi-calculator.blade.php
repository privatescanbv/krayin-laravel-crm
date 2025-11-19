@props(['height', 'weight', 'showLabel' => true])

@if($height && $weight)
    @php
        $heightInMeters = $height / 100;
        $bmi = round($weight / ($heightInMeters * $heightInMeters), 1);

        // BMI categories and colors
        if ($bmi < 18.5) {
            $bmiCategory = 'Ondergewicht';
            $bmiColor = 'bg-blue-500';
            $bmiTextColor = 'text-blue-700';
            $bmiBgColor = 'bg-blue-50';
        } elseif ($bmi < 25) {
            $bmiCategory = 'Normaal gewicht';
            $bmiColor = 'bg-succes';
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

    <div class="mt-4 p-3 {{ $bmiBgColor }} rounded-lg border bg-white dark:border-gray-600 dark:bg-opacity-20">
        <div class="flex justify-between items-center mb-2">
            @if($showLabel)
                <span class="text-gray-600 dark:text-gray-400">BMI:</span>
            @endif
            <span class="font-bold {{ $bmiTextColor }} dark:text-white">{{ $bmi }} - {{ $bmiCategory }}</span>
        </div>

        <!-- BMI Visual Bar -->
        <div class="relative">
            <div class="w-full h-6 bg-gray-200 rounded-full overflow-hidden dark:bg-gray-700">
                <!-- BMI scale background -->
                <div class="h-full flex">
                    <div class="bg-blue-300 flex-1 dark:bg-blue-600"></div>      <!-- Underweight -->
                    <div class="bg-green-300 flex-1 dark:bg-green-600"></div>    <!-- Normal -->
                    <div class="bg-yellow-300 flex-1 dark:bg-yellow-600"></div>   <!-- Overweight -->
                    <div class="bg-red-300 flex-1 dark:bg-red-600"></div>      <!-- Obese -->
                </div>
            </div>

            <!-- BMI indicator -->
            <div class="absolute top-0 h-6 w-1 {{ $bmiColor }} rounded-full transform -translate-x-1/2"
                 style="left: {{ $bmiPosition }}%;">
            </div>
        </div>

        <!-- BMI scale labels -->
        <div class="flex justify-between text-xs text-gray-500 mt-1 dark:text-gray-400">
            <span>15</span>
            <span>18.5</span>
            <span>25</span>
            <span>30</span>
            <span>40</span>
        </div>
    </div>
@endif
