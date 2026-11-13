<script setup>
import { useI18n } from 'vue-i18n'
import { useJourneyHome } from '@/composables/useJourneyHome'
import { useLevelHero } from '@/composables/useLevelHero'
import { useAccreditedPerformance } from '@/composables/useAccreditedPerformance'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import LevelHeroCard from '@/components/level/LevelHeroCard.vue'
import JourneyHero from './JourneyHero.vue'
import PathTiles from '@/components/paths/PathTiles.vue'
import JourneyKpis from './JourneyKpis.vue'
import JourneyPerformance from './JourneyPerformance.vue'
import NextStepCard from './NextStepCard.vue'
import GlanceNotificationsCard from './GlanceNotificationsCard.vue'

const { t } = useI18n()
const { sources, status, load } = useJourneyHome()
const {
  tier,
  certificate,
  ranges,
  reassessment,
  action,
  nextLevelTo,
  playKey,
  loading: heroLoading,
  failed: heroFailed,
  retry: retryHero,
} = useLevelHero()
const performance = useAccreditedPerformance()
</script>

<template>
  <div class="journey-home" :aria-busy="status === 'loading'">
    <section class="b-band journey-home__band" data-testid="journey-band">
      <div class="b-band__inner">
        <JourneyHero />
        <LevelHeroCard
          :tier="tier"
          :certificate="certificate"
          :ranges="ranges"
          :reassessment="reassessment"
          :action="action"
          :next-level-to="nextLevelTo"
          :play-key="playKey"
          :loading="heroLoading"
          :failed="heroFailed"
          @retry="retryHero"
        />
      </div>
    </section>

    <div class="journey-home__content">
      <div v-if="status === 'loading'" class="journey-home__loading">
        <Skeleton variant="block" :count="3" />
      </div>
      <ErrorState
        v-else-if="status === 'error'"
        :message="t('portal.home.loadError')"
        @retry="load"
      />
      <template v-else>
        <PathTiles :paths="sources.board?.paths ?? null" />
        <NextStepCard v-if="sources.board?.nextStep" :step="sources.board.nextStep" />
        <JourneyKpis
          :accredited-series="performance.series.value"
          :self-assessments="sources.selfAssessments"
          :certificates="sources.certificates"
          :evidence-count="sources.board?.acceptedEvidenceCount ?? null"
        />
        <JourneyPerformance
          :criteria="performance.criteria.value"
          :series="performance.series.value"
          :ranges="ranges"
          :has-previous="performance.hasPrevious.value"
        />
        <GlanceNotificationsCard class="journey-home__notifications" />
      </template>
    </div>
  </div>
</template>
