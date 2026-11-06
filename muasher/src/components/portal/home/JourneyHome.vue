<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useJourneyHome } from '@/composables/useJourneyHome'
import { useEntityLevel } from '@/composables/useEntityLevel'
import { useCertificateLevels } from '@/composables/useCertificateLevels'
import { useAccreditedPerformance } from '@/composables/useAccreditedPerformance'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import LevelHeroCard from '@/components/level/LevelHeroCard.vue'
import JourneyHero from './JourneyHero.vue'
import JourneyPath from './JourneyPath.vue'
import JourneyKpis from './JourneyKpis.vue'
import JourneyPerformance from './JourneyPerformance.vue'
import NextStepCard from './NextStepCard.vue'
import GlanceNotificationsCard from './GlanceNotificationsCard.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { usePermissions } from '@/composables/usePermissions'
import { accessFor } from '@/constants/routeAccess'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { NEXT_LEVEL_TIERS } from '@/constants/levels'

const { t } = useI18n()
const { sources, status, journey, load } = useJourneyHome()
const level = useEntityLevel()
const { ranges } = useCertificateLevels()
const performance = useAccreditedPerformance()
const { canAccess } = usePermissions()
const showsNextLevelLink = computed(
  () =>
    NEXT_LEVEL_TIERS.includes(level.tier.value) &&
    canAccess(accessFor(ROUTE_NAMES.PORTAL_TOOL_STRENGTHS)),
)
</script>

<template>
  <div class="journey-home" :aria-busy="status === 'loading'">
    <section class="b-band journey-home__band" data-testid="journey-band">
      <div class="b-band__inner">
        <JourneyHero :journey="status === 'success' ? journey : null" />
        <LevelHeroCard
          :tier="level.tier.value"
          :certificate="level.certificate.value"
          :ranges="ranges"
          :reassessment="level.reassessment.value"
        >
          <template #actions>
            <router-link class="btn btn--on-band" :to="{ name: level.miniCard.value.routeName }">
              {{ t(level.miniCard.value.actionKey) }}
            </router-link>
            <router-link
              v-if="showsNextLevelLink"
              class="level-hero__link"
              :to="{ name: ROUTE_NAMES.PORTAL_TOOL_STRENGTHS }"
              data-testid="hero-next-level"
            >
              {{ t('level.hero.nextLevelLink') }}
              <AppIcon name="arrow-right" />
            </router-link>
          </template>
        </LevelHeroCard>
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
        <JourneyKpis
          :accredited-series="performance.series.value"
          :self-assessments="sources.selfAssessments"
          :certificates="sources.certificates"
          :evidence-count="sources.board?.acceptedEvidenceCount ?? null"
        />
        <div class="journey-home__pair">
          <NextStepCard class="journey-home__next" :step="journey.nextStep" />
          <JourneyPath class="journey-home__path" :journey="journey" />
        </div>
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
