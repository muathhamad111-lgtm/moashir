<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { CONTACT_INBOX_STATUSES } from '@/constants/contactInbox'
import { formatNumber } from '@/utils/formatNumber'

const STAT_KEYS = {
  new: 'new',
  assigned: 'assigned',
  replied: 'replied',
  'awaiting-reply': 'awaitingReply',
}

const props = defineProps({
  modelValue: { type: String, default: '' },
  stats: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])
const { t, n } = useI18n()

const chips = computed(() => {
  const counts = CONTACT_INBOX_STATUSES.map((status) => props.stats?.[STAT_KEYS[status]] ?? 0)
  return [
    { value: '', label: t('contactInbox.split.all'), count: counts.reduce((a, b) => a + b, 0) },
    ...CONTACT_INBOX_STATUSES.map((status, index) => ({
      value: status,
      label: t(`contactInbox.statuses.${status}`),
      count: counts[index],
    })),
  ]
})
</script>

<template>
  <div class="contact-chips" role="group" :aria-label="t('contactInbox.split.statusFilter')">
    <button
      v-for="chip in chips"
      :key="chip.value || 'all'"
      type="button"
      class="contact-chips__chip"
      :class="chip.value && `contact-chips__chip--${chip.value}`"
      :aria-pressed="modelValue === chip.value"
      @click="emit('update:modelValue', chip.value)"
    >
      <span>{{ chip.label }}</span>
      <span class="contact-chips__count">{{ stats ? formatNumber(n, chip.count) : '–' }}</span>
    </button>
  </div>
</template>
