<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import PageLoading from '@/components/feedback/PageLoading.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import OverviewPeriodPicker from './OverviewPeriodPicker.vue'
import OverviewCards from './OverviewCards.vue'
import OverviewCharts from './OverviewCharts.vue'
import OverviewPrintButton from './OverviewPrintButton.vue'
import { useExecutiveOverview } from '@/composables/useExecutiveOverview'
import { useOverviewRangeText } from '@/composables/useOverviewRangeText'
import { formatRiyadhDateTime } from '@/utils/datetime'

const { t, locale } = useI18n()
const state = useExecutiveOverview()
const { overview, status, errorMessage, fieldErrors, period, selection, customFrom, customTo } =
  state

const { periodText, previousText } = useOverviewRangeText(overview)
const generatedAt = computed(() =>
  overview.value?.generatedAt ? formatRiyadhDateTime(overview.value.generatedAt, locale.value) : '',
)
const isInvalid = computed(() => Object.keys(fieldErrors.value).length > 0)

onMounted(state.load)
</script>

<template>
  <section
    class="executive-overview"
    aria-labelledby="executive-overview-title"
    data-testid="executive-overview"
  >
    <header class="executive-overview__head">
      <div class="executive-overview__heading">
        <h2 id="executive-overview-title" class="executive-overview__title">
          {{ t('dashboard.overview.title') }}
        </h2>
        <p v-if="periodText" class="executive-overview__period">
          {{ periodText }}
          <span class="executive-overview__compare">{{ previousText }}</span>
        </p>
      </div>
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
        <OverviewPrintButton :disabled="status !== 'success'" />
      </div>
    </header>

    <PageLoading v-if="status === 'loading' && !overview" />
    <ErrorState
      v-else-if="status === 'error' && !isInvalid"
      :message="errorMessage || t('dashboard.loadError')"
      @retry="state.load"
    />
    <div v-if="overview" class="executive-overview__body" :aria-busy="status === 'loading'">
      <OverviewCards :cards="overview.cards" />
      <OverviewCharts :overview="overview" />
      <p v-if="generatedAt" class="executive-overview__generated">
        {{ t('dashboard.generatedAt') }}
        <time :datetime="overview.generatedAt">{{ generatedAt }}</time>
      </p>
    </div>
  </section>
</template>
