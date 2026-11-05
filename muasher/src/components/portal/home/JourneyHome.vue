<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useJourneyHome } from '@/composables/useJourneyHome'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import JourneyHero from './JourneyHero.vue'
import JourneyPath from './JourneyPath.vue'
import NextStepCard from './NextStepCard.vue'
import GlanceResultCard from './GlanceResultCard.vue'
import GlanceCertificateCard from './GlanceCertificateCard.vue'
import GlanceNotificationsCard from './GlanceNotificationsCard.vue'

const { t } = useI18n()
const { sources, status, journey, load } = useJourneyHome()

const certificate = computed(() => journey.value.stations[3])
const issuingSince = computed(() => {
  const { accredited } = sources
  if (certificate.value.state === 'current') return accredited?.latestClosed?.completedAt ?? null
  return accredited?.active?.createdAt ?? null
})
const latestCertificate = computed(
  () =>
    sources.certificates.find((item) => item.status === 'issued') ??
    sources.certificates[0] ??
    null,
)
</script>

<template>
  <div class="journey-home" :aria-busy="status === 'loading'">
    <JourneyHero :journey="status === 'success' ? journey : null" />

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
        <NextStepCard class="journey-home__next" :step="journey.nextStep" />
        <JourneyPath class="journey-home__path" :journey="journey" />
        <section class="journey-home__glance" aria-labelledby="journey-glance-title">
          <h2 id="journey-glance-title" class="journey-home__glance-title">
            {{ t('portal.home.glance.title') }}
          </h2>
          <div class="journey-home__glance-grid">
            <GlanceResultCard :result="sources.board?.latestResult ?? null" />
            <GlanceCertificateCard
              :certificate="latestCertificate"
              :issuing-since="issuingSince"
              :is-issuing="certificate.state === 'current'"
            />
            <GlanceNotificationsCard />
          </div>
        </section>
      </template>
    </div>
  </div>
</template>
