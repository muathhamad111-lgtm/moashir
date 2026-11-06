<script setup>
import { computed } from 'vue'
import { ASSESSMENT_NOTICE_ICONS as ICONS } from '@/constants/assessment'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'info',
    validator: (value) => Object.keys(ICONS).includes(value),
  },
  title: { type: String, required: true },
  message: { type: String, default: '' },
  live: { type: Boolean, default: false },
})

const icon = computed(() => ICONS[props.variant])
const role = computed(() => (props.live ? 'alert' : 'note'))
</script>

<template>
  <aside class="assessment-notice" :class="`assessment-notice--${variant}`" :role="role">
    <AppIcon :name="icon" class="assessment-notice__icon" />
    <div class="assessment-notice__body">
      <p class="assessment-notice__title">{{ title }}</p>
      <p v-if="message" class="assessment-notice__message">{{ message }}</p>
      <slot />
    </div>
    <div v-if="$slots.action" class="assessment-notice__action">
      <slot name="action" />
    </div>
  </aside>
</template>
