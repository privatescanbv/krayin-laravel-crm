<div class="flex items-center justify-between mb-2">
    <p class="text-sm font-semibold text-activity-task-text dark:text-blue-100">Mogelijke matches gevonden</p>
{{--    <button type="button" class="text-xs text-blue-700 underline" @click="clearSuggestions">verberg</button>--}}
  </div>
  <template v-for="section in (typeof personSuggestionSections === 'function' ? personSuggestionSections(suggestions) : [{ key: 'all', title: null, items: suggestions }])" :key="section.key">
    <p v-if="section.title" class="text-xs font-semibold text-gray-700 dark:text-gray-200 mt-3 first:mt-0">@{{ section.title }}</p>
    <ul class="space-y-2 max-h-[420px] overflow-auto pr-1" :class="{ 'mt-2': section.title }">
      <li v-for="s in section.items" :key="'sug-'+s.id" class="flex items-center justify-between gap-3">
        <div class="min-w-0">
          <div class="text-sm font-medium dark:text-white truncate">@{{ s.name }}</div>
          <div class="text-xs text-gray-700 dark:text-gray-200 truncate" v-if="s.date_of_birth">@{{ formatDate(s.date_of_birth) }}</div>
          <div class="text-xs text-gray-600 dark:text-gray-300 truncate">
            <span v-if="(s.emails||[]).length">@{{ (s.emails[0]||{}).value }}</span>
            <span v-if="(s.phones||[]).length && (s.emails||[]).length"> · </span>
            <span v-if="(s.phones||[]).length">@{{ (s.phones[0]||{}).value }}</span>
          </div>
          <div v-if="(s.match_reasons||[]).length" class="mt-1 flex flex-wrap gap-1">
            <span
              v-for="reason in s.match_reasons"
              :key="reason"
              class="px-1.5 py-0.5 text-[10px] leading-tight rounded-full"
              :class="reason === 'email' || reason === 'phone'
                ? 'bg-status-active-text/15 text-status-active-text dark:bg-green-900 dark:text-green-200'
                : reason === 'first_name_differs'
                  ? 'bg-status-on_hold-text/15 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                  : 'bg-neutral-bg text-gray-700 dark:bg-gray-800 dark:text-gray-200'"
            >@{{ typeof suggestionReasonLabel === 'function' ? suggestionReasonLabel(reason) : reason }}</span>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <span class="px-2 py-0.5 text-xs rounded-full bg-neutral-bg dark:bg-gray-800 dark:text-gray-200">@{{ Math.round(s.match_score_percentage || s.match_score || 0) }}% match</span>
          <a :href="`/admin/contacts/persons/view/${s.id}`" target="_blank" rel="noopener" class="text-xs text-blue-700 underline">Bekijken</a>
          <button type="button" class="secondary-button" @click="{{ $buttonHandler ?? 'selectSuggestion' }}(s)">{{ $buttonText ?? 'Koppelen' }}</button>
        </div>
      </li>
    </ul>
  </template>

@include('adminc.components.person-suggestion-helpers')
