<div class="flex items-center justify-between mb-2">
    <p class="text-sm font-semibold text-blue-800 dark:text-blue-100">Mogelijke matches gevonden</p>
    <button type="button" class="text-xs text-blue-700 underline" @click="clearSuggestions">verberg</button>
  </div>
  <ul class="space-y-2 max-h-[420px] overflow-auto pr-1">
    <li v-for="s in suggestions" :key="'sug-'+(s.id||s.unique_id)" class="flex items-center justify-between gap-3">
      <div class="min-w-0">
        <div class="text-sm font-medium dark:text-white truncate">@{{ s.name }}</div>
        <div class="text-xs text-gray-700 dark:text-gray-200 truncate" v-if="s.date_of_birth">@{{ formatDate(s.date_of_birth) }}</div>
        <div class="text-xs text-gray-600 dark:text-gray-300 truncate">
          <span v-if="(s.emails||[]).length">@{{ (s.emails[0]||{}).value }}</span>
          <span v-if="(s.phones||[]).length && (s.emails||[]).length"> · </span>
          <span v-if="(s.phones||[]).length">@{{ (s.phones[0]||{}).value }}</span>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-800 dark:text-gray-200">@{{ Math.round(s.match_score_percentage || s._client_match || 0) }}% match</span>
        <a :href="`/admin/contacts/persons/view/${s.id}`" target="_blank" rel="noopener" class="text-xs text-blue-700 underline">Bekijken</a>
        <button type="button" class="secondary-button" @click="{{ $buttonHandler ?? 'selectSuggestion' }}(s)">{{ $buttonText ?? 'Koppelen' }}</button>
      </div>
    </li>
  </ul>

