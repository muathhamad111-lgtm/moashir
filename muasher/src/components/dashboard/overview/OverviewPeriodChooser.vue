<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppPopover from '@/components/ui/AppPopover.vue'
import OverviewPeriodGrid from './OverviewPeriodGrid.vue'
import { OVERVIEW_PERIODS } from '@/constants/dashboardOverview'
import { calendarOf, monthLabel, selectableYears } from '@/utils/dashboard/overviewCalendar'

// The month, quarter or year of the chosen period type, picked from a popover (a sheet on phones).
const props = defineProps({
  period: { type: String, required: true },
  selection: { type: Object, required: true },
  today: { type: String, required: true },
})

const emit = defineEmits(['choose'])
const { t, locale } = useI18n()
const popoverRef = ref(null)
const viewYear = ref(props.selection.year)
const years = computed(() => selectableYears(props.today))
const now = computed(() => calendarOf(props.today))
const steps = computed(() => props.period !== OVERVIEW_PERIODS.YEAR)

const isCurrent = computed(() => {
  const { year, month, quarter } = props.selection
  if (year !== now.value.year) return false
  if (props.period === OVERVIEW_PERIODS.MONTH) return month === now.value.month
  if (props.period === OVERVIEW_PERIODS.QUARTER) return quarter === now.value.quarter
  return true
})

const label = computed(() => {
  const { year, month, quarter } = props.selection
  if (props.period === OVERVIEW_PERIODS.MONTH) return monthLabel(year, month, locale.value)
  if (props.period === OVERVIEW_PERIODS.QUARTER)
    return t('dashboard.overview.period.quarterOf', {
      quarter: t(`dashboard.overview.period.quarters.${quarter}`),
      year,
    })
  return `${year}`
})

function shiftYear(step) {
  viewYear.value += step
}

function choose(fields) {
  emit('choose', fields)
  popoverRef.value?.close()
}
</script>

<template>
  <AppPopover
    ref="popoverRef"
    class="overview-period__chooser"
    trigger-class="overview-period__trigger"
    :label="t(`dashboard.overview.period.pick.${period}`)"
    :trigger-label="t('dashboard.overview.period.choose', { current: label })"
    @open="viewYear = selection.year"
  >
    <template #trigger>
      <AppIcon name="calendar" />
      <span class="overview-period__current">{{ label }}</span>
      <span v-if="isCurrent" class="overview-period__to-date">
        {{ t('dashboard.overview.period.toDate') }}
      </span>
      <AppIcon name="chevron-sm-down" />
    </template>
    <template #default="{ headingId, isSheet }">
      <div class="period-chooser">
        <h3 v-if="!isSheet" :id="headingId" class="period-chooser__title" tabindex="-1">
          {{ t(`dashboard.overview.period.pick.${period}`) }}
        </h3>
        <div v-if="steps" class="period-chooser__years">
          <button
            type="button"
            class="period-chooser__step"
            :aria-label="t('dashboard.overview.period.previousYear')"
            :disabled="viewYear <= years[0]"
            @click="shiftYear(-1)"
          >
            <AppIcon name="chevron-left" />
          </button>
          <span class="period-chooser__year" aria-live="polite">{{ viewYear }}</span>
          <button
            type="button"
            class="period-chooser__step"
            :aria-label="t('dashboard.overview.period.nextYear')"
            :disabled="viewYear >= years[years.length - 1]"
            @click="shiftYear(1)"
          >
            <AppIcon name="chevron-right" />
          </button>
        </div>
        <OverviewPeriodGrid
          :period="period"
          :year="viewYear"
          :selection="selection"
          :today="today"
          @choose="choose"
        />
      </div>
    </template>
  </AppPopover>
</template>
