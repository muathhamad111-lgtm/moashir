<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import PageLoading from '@/components/feedback/PageLoading.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import OverviewPeriodPicker from './OverviewPeriodPicker.vue'
import OverviewCards from './OverviewCards.vue'
import OverviewCharts from './OverviewCharts.vue'
import { useExecutiveOverview } from '@/composables/useExecutiveOverview'
import { longDayLabel } from '@/utils/charts/bucketLabels'
import { formatRiyadhDateTime } from '@/utils/datetime'

const { t, locale } = useI18n()
const state = useExecutiveOverview()
const { overview, status, errorMessage, fieldErrors, period, customFrom, customTo } = state

function rangeText(window) {
  return window?.from
    ? t('dashboard.overview.range', {
        from: longDayLabel(window.from, locale.value),
        to: longDayLabel(window.to, locale.value),
      })
    : ''
}

const periodText = computed(() => rangeText(overview.value?.period))
const previousText = computed(() =>
  overview.value
    ? t('dashboard.overview.comparedWith', { range: rangeText(overview.value.previousPeriod) })
    : '',
)
const generatedAt = computed(() =>
  overview.value?.generatedAt ? formatRiyadhDateTime(overview.value.generatedAt, locale.value) : '',
)
const isInvalid = computed(() => Object.keys(fieldErrors.value).length > 0)

function print() {
  window.print()
}

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
          :from="customFrom"
          :to="customTo"
          :errors="fieldErrors"
          @preset="state.choosePreset"
          @custom="state.applyCustom"
        />
        <BaseButton
          class="executive-overview__print"
          variant="secondary"
          icon="printer"
          :disabled="status !== 'success'"
          @click="print"
        >
          {{ t('dashboard.overview.print') }}
        </BaseButton>
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
