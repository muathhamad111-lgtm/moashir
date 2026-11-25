<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import OverviewPeriodPicker from './OverviewPeriodPicker.vue'
import AssessorOverviewCards from './AssessorOverviewCards.vue'
import AssessorOverviewCharts from './AssessorOverviewCharts.vue'
import { usePeriodOverview } from '@/composables/usePeriodOverview'
import { dashboardService } from '@/services/dashboardService'
import { useOverviewRangeText } from '@/composables/useOverviewRangeText'

// The assessor's board: the live figures of the board summary, then the period figures and charts.
defineProps({
  liveCards: { type: Array, required: true },
})

const { t } = useI18n()
const state = usePeriodOverview((query) => dashboardService.getAssessorOverview(query))
const { overview, status, errorMessage, fieldErrors, period, selection, customFrom, customTo } =
  state

const { periodText, previousText } = useOverviewRangeText(overview)
const isInvalid = computed(() => Object.keys(fieldErrors.value).length > 0)

onMounted(state.load)
</script>

<template>
  <div class="assessor-overview" data-testid="assessor-overview">
    <header class="executive-overview__head">
      <p v-if="periodText" class="executive-overview__period">
        {{ periodText }}
        <span class="executive-overview__compare">{{ previousText }}</span>
      </p>
      <div class="executive-overview__tools">
        <OverviewPeriodPicker
          :period="period"
          :selection="selection"
          :from="customFrom"
          :to="customTo"
          :errors="fieldErrors"
          @preset="state.choosePreset"
          @calendar="state.chooseCalendar"
          @custom="state.applyCustom"
        />
      </div>
    </header>
    <AssessorOverviewCards :live-cards="liveCards" :period-cards="overview?.cards ?? []" />
    <div
      v-if="status === 'loading' && !overview"
      class="executive-overview__loading"
      aria-busy="true"
    >
      <Skeleton variant="block" :count="2" />
    </div>
    <ErrorState
      v-else-if="status === 'error' && !isInvalid"
      :message="errorMessage || t('dashboard.loadError')"
      @retry="state.load"
    />
    <div v-if="overview" class="executive-overview__body" :aria-busy="status === 'loading'">
      <AssessorOverviewCharts :overview="overview" :live-cards="liveCards" />
    </div>
  </div>
</template>
