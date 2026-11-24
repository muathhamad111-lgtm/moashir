<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import LedgerStatusBadge from './LedgerStatusBadge.vue'

// A total next to its split by status; each status is a toggle that filters the operations list.
defineProps({
  icon: { type: String, required: true },
  label: { type: String, required: true },
  total: { type: String, required: true },
  cells: { type: Array, required: true },
  paidTotal: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  formatCount: { type: Function, required: true },
  // The status of this card that filters the list now ('' when none).
  active: { type: String, default: '' },
})

const emit = defineEmits(['select'])
const { t } = useI18n()
</script>

<template>
  <section class="finance-overview__card finance-overview__split-card">
    <div class="finance-overview__summary">
      <span class="finance-overview__icon" aria-hidden="true"><AppIcon :name="icon" /></span>
      <div class="finance-overview__main">
        <Skeleton v-if="loading" variant="text" />
        <strong v-else class="finance-overview__value">{{ total }}</strong>
        <h2 class="finance-overview__label">{{ label }}</h2>
      </div>
    </div>
    <ul class="finance-overview__split">
      <li v-for="cell in cells" :key="cell.status">
        <button
          type="button"
          class="finance-overview__cell"
          :aria-pressed="cell.status === active"
          :title="t(`finance.operations.statuses.${cell.status}.label`)"
          :aria-label="
            t('finance.page.cards.filterBy', {
              status: t(`finance.operations.statuses.${cell.status}.label`),
              count: formatCount(cell.count),
            })
          "
          @click="emit('select', cell.status)"
        >
          <Skeleton v-if="loading" variant="text" />
          <strong v-else class="finance-overview__count">{{ formatCount(cell.count) }}</strong>
          <LedgerStatusBadge :status="cell.status" short />
          <span
            v-if="paidTotal && cell.status === 'paid' && !loading"
            class="finance-overview__amount"
            >{{ paidTotal }}</span
          >
        </button>
      </li>
    </ul>
  </section>
</template>
