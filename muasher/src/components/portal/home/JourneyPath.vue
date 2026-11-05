<script setup>
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'
import JourneyStation from './JourneyStation.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  journey: { type: Object, required: true },
})

const { t, n } = useI18n()
</script>

<template>
  <section class="journey-path" aria-labelledby="journey-path-title">
    <header class="journey-path__header">
      <div class="journey-path__heading">
        <h2 id="journey-path-title" class="journey-path__title">
          {{ t('portal.home.path.title') }}
        </h2>
        <p class="journey-path__subtitle">{{ t('portal.home.path.subtitle') }}</p>
      </div>
      <span class="journey-path__badge">
        {{
          t('portal.home.path.position', {
            current: formatNumber(n, journey.currentNumber),
            total: formatNumber(n, journey.stations.length),
          })
        }}
      </span>
    </header>
    <ol class="journey-path__stations">
      <JourneyStation
        v-for="(station, index) in journey.stations"
        :key="station.key"
        :station="station"
        :number="index + 1"
      />
    </ol>
    <p class="journey-path__footer">
      <AppIcon name="flag" />
      <span>{{ t('portal.home.path.after') }}</span>
      <span class="journey-path__soon">{{ t('portal.home.path.soon') }}</span>
    </p>
  </section>
</template>
