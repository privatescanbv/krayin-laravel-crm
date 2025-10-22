@php
    $weekdayBlocks = $weekdayBlocks ?? [];
    // Ensure weekdayBlocks is always an array
    if (!is_array($weekdayBlocks)) {
        $weekdayBlocks = [];
    }
@endphp

<x-admin::form.control-group>
    <x-admin::form.control-group.label class="required">
        @lang('admin::app.settings.shifts.fields.time_blocks')
    </x-admin::form.control-group.label>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @for($day=1; $day<=7; $day++)
            @php 
                $blocks = $weekdayBlocks[$day] ?? [];
                // Ensure blocks is always an array
                if (!is_array($blocks)) {
                    $blocks = [];
                }
            @endphp
            <div class="rounded border border-gray-200 p-3 dark:border-gray-800">
                <div class="mb-2 text-sm font-semibold">
                    {{ [1=>trans('admin::app.monday'),2=>trans('admin::app.tuesday'),3=>trans('admin::app.wednesday'),4=>trans('admin::app.thursday'),5=>trans('admin::app.friday'),6=>trans('admin::app.saturday'),7=>trans('admin::app.sunday')][$day] }}
                </div>
                <div class="flex flex-col gap-2" id="weekday-{{ $day }}-blocks">
                    @foreach($blocks as $i => $block)
                        <div class="flex items-center gap-2">
                            <input class="min-w-[120px] rounded border border-gray-300 p-2 dark:border-gray-800 dark:bg-gray-900" name="weekday_time_blocks[{{ $day }}][{{ $i }}][from]" value="{{ $block['from'] ?? '' }}" placeholder="08:00">
                            <input class="min-w-[120px] rounded border border-gray-300 p-2 dark:border-gray-800 dark:bg-gray-900" name="weekday_time_blocks[{{ $day }}][{{ $i }}][to]" value="{{ $block['to'] ?? '' }}" placeholder="12:00">
                            <button type="button" class="icon-delete text-red-600" onclick="window.removeTimeBlock(this)"></button>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="secondary-button mt-2" onclick="window.addTimeBlock({{ $day }})">+ @lang('admin::app.add')</button>
            </div>
        @endfor
    </div>

    <x-admin::form.control-group.error control-name="weekday_time_blocks" />
</x-admin::form.control-group>

@pushOnce('scripts')
    <script>
        window.addTimeBlock = function(day){
            var container = document.getElementById('weekday-' + day + '-blocks');
            if(!container) return;
            var index = container.children.length;
            var row = document.createElement('div');
            row.className = 'flex items-center gap-2';
            row.innerHTML = '<input class="min-w-[120px] rounded border border-gray-300 p-2 dark:border-gray-800 dark:bg-gray-900" name="weekday_time_blocks['+day+']['+index+'][from]" placeholder="08:00">'
                + '<input class="min-w-[120px] rounded border border-gray-300 p-2 dark:border-gray-800 dark:bg-gray-900" name="weekday_time_blocks['+day+']['+index+'][to]" placeholder="12:00">'
                + '<button type="button" class="icon-delete text-red-600" onclick="window.removeTimeBlock(this)"></button>';
            container.appendChild(row);
        };

        window.removeTimeBlock = function(button){
            var row = button && button.parentElement;
            if(row) row.remove();
        };
    </script>
@endPushOnce


