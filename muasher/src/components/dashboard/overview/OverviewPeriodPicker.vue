<script setup>
import { useI18n } from 'vue-i18n'
import OverviewPeriodChooser from './OverviewPeriodChooser.vue'
import OverviewRangeChooser from './OverviewRangeChooser.vue'
import { OVERVIEW_PERIODS, OVERVIEW_PRESETS } from '@/constants/dashboardOverview'
import { riyadhDateKey } from '@/utils/datetime'

const props = defineProps({
  period: { type: String, required: true },
  selection: { type: Object, required: true },
  from: { type: String, default: '' },
  to: { type: String, default: '' },
  errors: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['preset', 'calendar', 'custom'])
const { t } = useI18n()
const today = riyadhDateKey(new Date())

function firstError() {
  return Object.values(props.errors ?? {})[0]?.[0] ?? ''
}
</script>

<template>
  <div class="overview-period">
    <div
      class="overview-period__presets overview-period__presets--types"
      role="group"
      :aria-label="t('dashboard.overview.period.type')"
    >
      <button
        v-for="preset in OVERVIEW_PRESETS"
        :key="preset"
        type="button"
        class="overview-period__preset"
        :class="{ 'is-active': period === preset }"
        :aria-pressed="period === preset ? 'true' : 'false'"
        :data-period="preset"
        @click="emit('preset', preset)"
      >
        {{ t(`dashboard.overview.period.presets.${preset}`) }}
      </button>
    </div>
    <OverviewRangeChooser
      v-if="period === OVERVIEW_PERIODS.CUSTOM"
      :from="from"
      :to="to"
      :today="today"
      @choose="(first, last) => emit('custom', first, last)"
    />
    <OverviewPeriodChooser
      v-else
      :period="period"
      :selection="selection"
      :today="today"
      @choose="emit('calendar', $event)"
    />
    <p v-if="firstError()" class="overview-period__error" role="alert">{{ firstError() }}</p>
  </div>
</template>
