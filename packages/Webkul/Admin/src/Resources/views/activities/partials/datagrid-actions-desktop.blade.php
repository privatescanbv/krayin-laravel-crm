<div class="flex items-center justify-end gap-1.5" v-if="available.actions.length">
    <!-- Standard Actions -->
    <span
        class="cursor-pointer rounded-md p-1.5 text-2xl text-gray-600 dark:text-gray-300 transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
        :class="action.icon"
        :title="action.title"
        v-for="action in record.actions"
        @click="performAction(action)"
    ></span>

    <!-- Assign to Me Button -->
    <button
        v-if="!record.user_id"
        class="ml-2 px-2 py-1 rounded bg-brand-privatescan-main text-white text-xs hover:bg-brand-privatescan-hover transition-colors"
        @click="assignToMe(record)"
        title="Aan mij toekennen"
    >
        Toekennen
    </button>

    <!-- Takeover Button -->
    <button
        v-if="record.user_id && record.user_id != {{ auth()->guard('user')->id() ?? 'null' }} && canTakeover"
        class="ml-2 px-2 py-1 rounded bg-brand-privatescan-accent text-white text-xs hover:bg-brand-privatescan-accenthover transition-colors"
        @click="takeoverActivity(record)"
        :title="'Overnemen van ' + (record.user && record.user.name ? record.user.name : 'onbekend')"
    >
        Overnemen
    </button>

    <!-- Unassign Button -->
    <button
        v-if="record.user_id == {{ auth()->guard('user')->id() ?? 'null' }}"
        class="ml-2 px-2 py-1 rounded bg-brand-privatescan-accent text-white text-xs hover:bg-brand-privatescan-accenthover transition-colors"
        @click="unassignActivity(record)"
        title="Ontkoppelen - maak beschikbaar voor anderen"
    >
        Ontkoppelen
    </button>
</div>

