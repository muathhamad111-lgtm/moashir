<script setup>
import { useI18n } from 'vue-i18n'
import { LEDGER_STATUS_VARIANTS } from '@/constants/finance'

// A thin bar split by status, each part as wide as its share. Screen readers get the same figures
// as a list; sighted viewers get each part's status and count on hover.
const props = defineProps({
  parts: { type: Array, required: true },
  label: { type: String, required: true },
  formatCount: { type: Function, required: true },
})

const { t } = useI18n()

function partLabel(part) {
  return t('finance.page.cards.statusCount', {
    status: t(`finance.page.cards.statuses.${part.status}`),
    count: props.formatCount(part.count),
  })
}
</script>

<template>
  <div class="status-bar">
    <div class="status-bar__track" aria-hidden="true">
      <span
        v-for="part in parts"
        :key="part.status"
        class="status-bar__part"
        :class="`status-bar__part--${LEDGER_STATUS_VARIANTS[part.status] ?? 'muted'}`"
        :title="partLabel(part)"
        :data-status="part.status"
        :style="{ flexGrow: part.count }"
      />
    </div>
    <ul class="status-bar__list" :aria-label="label">
      <li v-for="part in parts" :key="part.status">{{ partLabel(part) }}</li>
    </ul>
  </div>
</template>
