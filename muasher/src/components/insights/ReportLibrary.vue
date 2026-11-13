<script setup>
import { onMounted, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ComingSoonChip from '@/components/portal/ComingSoonChip.vue'
import ReportLibraryRow from './ReportLibraryRow.vue'
import { useReportLibrary } from '@/composables/useReportLibrary'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { insightsService } from '@/services/insightsService'
import { isoDate } from '@/utils/datetime'
import { versionLabelOf } from '@/utils/insights'

const props = defineProps({
  entityId: { type: Number, default: null },
  staff: { type: Boolean, default: false },
})

const { t, locale } = useI18n()
const library = useReportLibrary(toRef(props, 'entityId'))
const { status, rows, comingSoon, hasMore, loadingMore, moreFailed } = library
const downloads = useSignedDownload({
  errorPrefix: 'insights.board.errors',
  fallbackKey: 'insights.board.errors.failed',
})

function versionOf(result) {
  return (
    versionLabelOf(result.criteriaVersion, locale.value) ||
    t('insights.versionFallback', { number: result.criteriaVersion?.versionNumber ?? '' })
  )
}

const feedbackDownloads = useSignedDownload({
  errorPrefix: 'insights.board.errors',
  fallbackKey: 'insights.library.feedbackPdfFailed',
})

async function downloadFeedback(assessmentId, url) {
  const done = await feedbackDownloads.download(`feedback-${assessmentId}`, () =>
    Promise.resolve({ data: { url } }),
  )
  if (!done) library.load()
}

function busyFor(assessmentId) {
  return Boolean(
    downloads.busy[`board-${assessmentId}`] || feedbackDownloads.busy[`feedback-${assessmentId}`],
  )
}

function downloadBoard(assessmentId) {
  return downloads.download(`board-${assessmentId}`, () =>
    insightsService.exportBoardReport(assessmentId, { locale: locale.value }),
  )
}

onMounted(library.load)
</script>

<template>
  <section
    class="b-card report-library"
    aria-labelledby="report-library-title"
    data-testid="report-library"
  >
    <header class="report-library__header">
      <h2 id="report-library-title" class="b-card__title">{{ t('insights.library.title') }}</h2>
      <p class="report-library__subtitle">{{ t('insights.library.subtitle') }}</p>
    </header>
    <Skeleton v-if="status === 'loading'" variant="text" :count="4" />
    <ErrorState
      v-else-if="status === 'error'"
      :message="t('insights.loadError')"
      @retry="library.load"
    />
    <template v-else>
      <p v-if="!rows.length" class="report-library__empty">{{ t('insights.library.empty') }}</p>
      <section
        v-for="row in rows"
        :key="row.result.assessmentId"
        class="report-library__group"
        data-testid="library-group"
      >
        <h3 class="report-library__group-title">
          <bdi dir="ltr">{{ row.result.number }}</bdi>
          <span class="report-library__group-meta">
            {{
              t('insights.library.rowHeading', {
                date: isoDate(row.result.verifiedAt),
                version: versionOf(row.result),
              })
            }}
          </span>
        </h3>
        <ul class="report-library__items">
          <ReportLibraryRow
            v-for="item in row.items"
            :key="item.kind"
            :item="item"
            :assessment-id="row.result.assessmentId"
            :result="row.result"
            :staff="staff"
            :busy="busyFor(row.result.assessmentId)"
            @board-report="downloadBoard"
            @feedback-pdf="downloadFeedback(row.result.assessmentId, $event)"
          />
        </ul>
      </section>
      <BaseButton
        v-if="hasMore"
        variant="secondary"
        :loading="loadingMore"
        @click="library.loadMore"
      >
        {{ t('insights.library.more') }}
      </BaseButton>
      <p v-if="moreFailed" class="report-library__empty" role="alert">
        {{ t('insights.loadError') }}
      </p>
      <section v-if="comingSoon.length" class="report-library__group">
        <h3 class="report-library__group-title">{{ t('insights.library.comingSoonTitle') }}</h3>
        <ul class="report-library__items">
          <li v-for="item in comingSoon" :key="item.kind" class="library-row library-row--soon">
            <span class="library-row__name">
              <AppIcon name="clock" />
              {{ t(`insights.library.comingSoon.${item.kind}`) }}
            </span>
            <ComingSoonChip />
          </li>
        </ul>
      </section>
    </template>
  </section>
</template>
