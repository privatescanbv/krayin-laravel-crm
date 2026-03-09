<div class="flex w-full items-center justify-end gap-2" v-if="available.actions.length">
    <div class="flex items-center">
        <span
            class="cursor-pointer rounded-md p-1.5 text-2xl text-gray-600 dark:text-gray-300 transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
            :class="action.icon"
            :title="action.title"
            v-for="action in record.actions"
            @click="performAction(action)"
        ></span>
    </div>

    <button
        v-if="!record.user_id"
        class="px-2 py-1 rounded bg-brand-herniapoli-main text-white text-xs hover:text-activity-note-text transition-colors"
        @click="assignToMe(record)"
    >
        Toekennen
    </button>

    <button
        v-if="record.user_id && record.user_id != {{ auth()->guard('user')->id() ?? 'null' }} && canTakeover"
        class="ml-2 px-2 py-1 rounded bg-orange-500 text-white text-xs hover:bg-orange-600 transition-colors"
        @click="takeoverActivity(record)"
    >
        Overnemen
    </button>

    <button
        v-if="record.user_id == {{ auth()->guard('user')->id() ?? 'null' }}"
        class="ml-2 px-2 py-1 rounded bg-red-500 text-white text-xs hover:bg-red-600 transition-colors"
        @click="unassignActivity(record)"
    >
        Ontkoppelen
    </button>
</div>

