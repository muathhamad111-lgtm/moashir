<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import ExecutiveFacts from './ExecutiveFacts.vue'
import InsightsEntityLogo from './InsightsEntityLogo.vue'
import ExecutiveHighlights from './ExecutiveHighlights.vue'
import GapClassChip from './GapClassChip.vue'
import MaturityBandChip from './MaturityBandChip.vue'
import { GAP_COLUMNS, MATURITY_BANDS } from '@/constants/insights'
import { isoDate } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'
import { versionLabelOf } from '@/utils/insights'

const props = defineProps({
  summary: { type: Object, required: true },
})

const BANDS_DESCENDING = [...MATURITY_BANDS].reverse()
const { t, n, locale } = useI18n()
const result = computed(() => props.summary.result)
const entityName = computed(() => localizedText(props.summary.entity.name, locale.value))
const version = computed(
  () =>
    versionLabelOf(result.value.criteriaVersion, locale.value) ||
    t('insights.versionFallback', { number: result.value.criteriaVersion?.versionNumber ?? '' }),
)
</script>

<template>
  <section
    class="b-card executive-summary"
    aria-labelledby="executive-summary-title"
    data-testid="executive-summary"
  >
    <header class="executive-summary__header">
      <h2 id="executive-summary-title" class="b-card__title">
        {{ t('insights.board.summaryTitle') }}
      </h2>
      <div class="executive-summary__identity">
        <InsightsEntityLogo :url="summary.entity.logoUrl ?? null" :name="entityName" />
        <div>
          <p class="executive-summary__entity">{{ entityName }}</p>
          <i18n-t keypath="insights.board.identity" tag="p" class="executive-summary__line">
            <template #number>
              <bdi dir="ltr">{{ result.number }}</bdi>
            </template>
            <template #date>
              <bdi dir="ltr">{{ isoDate(result.verifiedAt) }}</bdi>
            </template>
            <template #version>{{ version }}</template>
          </i18n-t>
        </div>
      </div>
    </header>

    <ExecutiveFacts :summary="summary" />

    <section
      v-if="summary.assessorSummary"
      class="executive-summary__assessor"
      data-testid="executive-assessor-summary"
    >
      <h3 class="executive-summary__subtitle">
        <AppIcon name="chat" />
        {{ t('insights.board.assessorSummary') }}
      </h3>
      <figure class="executive-summary__quote">
        <figcaption>{{ t('insights.board.assessorQuote') }}</figcaption>
        <blockquote dir="auto" v-text="summary.assessorSummary.body"></blockquote>
      </figure>
    </section>

    <section class="executive-summary__profile">
      <h3 class="executive-summary__subtitle">
        <AppIcon name="chart-bar" />
        {{ t('insights.board.profile') }}
      </h3>
      <ul class="executive-summary__counts">
        <li v-for="column in GAP_COLUMNS" :key="column.gapClass">
          <GapClassChip :gap-class="column.gapClass" />
          <bdi dir="ltr">{{ n(summary.profile.classes[column.countKey] ?? 0) }}</bdi>
        </li>
        <li class="executive-summary__unit">{{ t('insights.board.profileSubs') }}</li>
      </ul>
      <ul class="executive-summary__counts">
        <li v-for="band in BANDS_DESCENDING" :key="band">
          <MaturityBandChip :band="band" />
          <bdi dir="ltr">{{ n(summary.profile.bands[band] ?? 0) }}</bdi>
        </li>
        <li class="executive-summary__unit">{{ t('insights.board.profileMains') }}</li>
      </ul>
    </section>

    <div class="executive-summary__highlights">
      <ExecutiveHighlights
        title-key="insights.board.strongAreas"
        :items="summary.strongAreas"
        quote-kind="strengths"
      />
      <ExecutiveHighlights
        title-key="insights.board.priorityGaps"
        :items="summary.priorityGaps"
        quote-kind="improvements"
      />
    </div>

    <footer class="executive-summary__footer">
      <i18n-t keypath="insights.board.footer" tag="p">
        <template #number>
          <bdi dir="ltr">{{ result.number }}</bdi>
        </template>
      </i18n-t>
      <p>
        {{
          t('insights.board.rulesFooter', {
            gaps: n(summary.rules.gapThresholdsVersion),
            bands: n(summary.rules.maturityBandsVersion),
          })
        }}
      </p>
    </footer>
  </section>
</template>
