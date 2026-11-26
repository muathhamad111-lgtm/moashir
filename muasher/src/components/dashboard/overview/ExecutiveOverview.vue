<script setup>
import { computed, markRaw, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import PageLoading from '@/components/feedback/PageLoading.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import OverviewCards from './OverviewCards.vue'
import OverviewPrintButton from './OverviewPrintButton.vue'
import BoardFilters from '../board/BoardFilters.vue'
import BoardChips from '../board/BoardChips.vue'
import BoardActivity from '../board/BoardActivity.vue'
import BoardEntities from '../board/BoardEntities.vue'
import BoardOperations from '../board/BoardOperations.vue'
import { useExecutiveOverview } from '@/composables/useExecutiveOverview'
import { useOverviewRangeText } from '@/composables/useOverviewRangeText'
import { formatRiyadhDateTime } from '@/utils/datetime'

const { t, locale } = useI18n()
const state = markRaw(useExecutiveOverview())
const { overview, status, errorMessage, fieldErrors } = state

const { periodText, previousText } = useOverviewRangeText(overview)
const generatedAt = computed(() =>
  overview.value?.generatedAt ? formatRiyadhDateTime(overview.value.generatedAt, locale.value) : '',
)
const isInvalid = computed(() => Object.keys(fieldErrors.value).length > 0)
// A refused period or filter is named under the title, since its field sits in the closed dialog.
const invalidText = computed(() => Object.values(fieldErrors.value)[0]?.[0] ?? '')

onMounted(state.load)
</script>

<template>
  <section
    class="executive-overview"
    aria-labelledby="executive-overview-title"
    data-testid="executive-overview"
  >
    <header class="board-head">
      <div class="executive-overview__heading">
        <h2 id="executive-overview-title" class="executive-overview__title">
          {{ t('dashboard.overview.title') }}
        </h2>
        <p v-if="periodText" class="executive-overview__period">
          {{ periodText }}
          <span class="executive-overview__compare">{{ previousText }}</span>
        </p>
      </div>
      <div class="board-head__tools">
        <BoardFilters :state="state" />
        <OverviewPrintButton :disabled="status !== 'success'" />
      </div>
      <BoardChips :state="state" />
      <p v-if="invalidText" class="board-head__error" role="alert">{{ invalidText }}</p>
    </header>

    <PageLoading v-if="status === 'loading' && !overview" />
    <ErrorState
      v-else-if="status === 'error' && !isInvalid"
      :message="errorMessage || t('dashboard.loadError')"
      @retry="state.load"
    />
    <div v-if="overview" class="executive-overview__body board" :aria-busy="status === 'loading'">
      <OverviewCards :cards="overview.cards" />
      <BoardActivity :overview="overview" />
      <BoardEntities :overview="overview" />
      <BoardOperations :overview="overview" />
      <p v-if="generatedAt" class="executive-overview__generated">
        {{ t('dashboard.generatedAt') }}
        <time :datetime="overview.generatedAt">{{ generatedAt }}</time>
      </p>
    </div>
  </section>
</template>
