<script setup>
import { defineProps, computed } from 'vue'
import { PhoneIcon, PhoneXMarkIcon, InboxIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  status: {
    type: String,
    required: true, // 'spoken' | 'unreachable' | 'voicemail'
  },
  size: {
    type: String,
    default: 'w-6 h-6', // tailwind size
  },
})

const iconMap = {
  spoken: PhoneIcon,
  not_reachable: PhoneXMarkIcon,
  voicemail_left: InboxIcon,
}

const colorMap = {
    spoken: 'text-status-active-text',
    not_reachable: 'text-red-500',
    voicemail_left: 'text-blue-500',
}

const resolvedStatus = computed(() => (props.status in iconMap ? props.status : 'spoken'))
</script>

<template>
  <component
    :is="iconMap[resolvedStatus]"
    :class="[size, colorMap[resolvedStatus]]"
  />

</template>


