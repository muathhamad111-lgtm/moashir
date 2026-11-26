<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import DashboardValue from '../DashboardValue.vue'
import OverviewDelta from './OverviewDelta.vue'

// The body of one overview tile: icon, value, label and the change from the previous period.
defineProps({
  card: { type: Object, required: true },
  label: { type: String, required: true },
  icon: { type: String, default: 'pie-chart' },
  lowerIsBetter: { type: Boolean, default: false },
})

const { t } = useI18n()
</script>

<template>
  <span class="overview-cards__icon" aria-hidden="true">
    <AppIcon :name="icon" />
  </span>
  <span class="overview-cards__body">
    <span class="overview-cards__value">
      <DashboardValue :value="card.value" :format="card.format" />
    </span>
    <span class="overview-cards__label">{{ label }}</span>
    <OverviewDelta
      v-if="card.previous !== null"
      :value="card.value"
      :previous="card.previous"
      :format="card.format"
      :lower-is-better="lowerIsBetter"
    />
    <span v-else class="overview-cards__previous">{{ t('dashboard.overview.currentState') }}</span>
  </span>
  <span v-if="card.previous !== null" class="overview-cards__previous overview-cards__was">
    {{ t('dashboard.overview.previousValue') }}
    <DashboardValue :value="card.previous" :format="card.format" />
  </span>
</template>
