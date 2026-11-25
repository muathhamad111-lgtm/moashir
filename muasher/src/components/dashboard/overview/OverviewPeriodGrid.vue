<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { OVERVIEW_PERIODS, OVERVIEW_QUARTERS } from '@/constants/dashboardOverview'
import {
  isMonthOpen,
  isQuarterOpen,
  monthName,
  quarterMonths,
  selectableYears,
} from '@/utils/dashboard/overviewCalendar'

const MONTHS = Array.from({ length: 12 }, (_, index) => index + 1)

// The choices of one period type: the months or quarters of a year, or the years. Future
// periods and periods before the boards' first month cannot be chosen.
const props = defineProps({
  period: { type: String, required: true },
  year: { type: Number, required: true },
  selection: { type: Object, required: true },
  today: { type: String, required: true },
})

const emit = defineEmits(['choose'])
const { t, locale } = useI18n()

const options = computed(() => {
  if (props.period === OVERVIEW_PERIODS.MONTH)
    return MONTHS.map((month) => ({
      key: month,
      label: monthName(month, locale.value),
      open: isMonthOpen(props.year, month, props.today),
      chosen: props.selection.year === props.year && props.selection.month === month,
      fields: { year: props.year, month },
    }))
  if (props.period === OVERVIEW_PERIODS.QUARTER)
    return OVERVIEW_QUARTERS.map((quarter) => ({
      key: quarter,
      label: t(`dashboard.overview.period.quarters.${quarter}`),
      hint: quarterMonths(quarter, locale.value),
      open: isQuarterOpen(props.year, quarter, props.today),
      chosen: props.selection.year === props.year && props.selection.quarter === quarter,
      fields: { year: props.year, quarter },
    }))
  return selectableYears(props.today).map((year) => ({
    key: year,
    label: `${year}`,
    open: true,
    chosen: props.selection.year === year,
    fields: { year },
  }))
})
</script>

<template>
  <ul class="period-grid" :class="`period-grid--${period}`">
    <li v-for="option in options" :key="option.key">
      <button
        type="button"
        class="period-grid__option"
        :class="{ 'is-chosen': option.chosen }"
        :aria-pressed="option.chosen ? 'true' : 'false'"
        :disabled="!option.open"
        @click="emit('choose', option.fields)"
      >
        <span>{{ option.label }}</span>
        <span v-if="option.hint" class="period-grid__hint">{{ option.hint }}</span>
      </button>
    </li>
  </ul>
</template>
