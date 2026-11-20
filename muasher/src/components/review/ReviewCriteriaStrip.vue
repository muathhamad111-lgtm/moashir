<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { localizedText } from '@/utils/localizedText'
import { STATION_STATES } from '@/utils/accredited/submissionProgress'

// The criteria of the request with their names and states (design §B.3.1, AS-2, AS-13): complete
// in pale green, partial in amber, not started neutral; the open one is outlined. It replaces the
// numbered dots and stays under the top bar while the page scrolls.
defineProps({
  // [{ id, name, scored, scorable, state }] from scoredStationsOf
  stations: { type: Array, required: true },
  activeId: { type: Number, default: null },
})

const emit = defineEmits(['select'])
const { t, locale } = useI18n()
</script>

<template>
  <nav class="review-strip" :aria-label="t('review.strip.label')">
    <ol class="review-strip__list">
      <li v-for="(station, index) in stations" :key="station.id">
        <button
          type="button"
          class="review-strip__step"
          :class="[`is-${station.state}`, { 'is-current': station.id === activeId }]"
          :aria-current="station.id === activeId ? 'step' : undefined"
          @click="emit('select', station.id)"
        >
          <span class="review-strip__marker" aria-hidden="true">
            <AppIcon v-if="station.state === STATION_STATES.COMPLETE" name="check" />
            <template v-else>{{ index + 1 }}</template>
          </span>
          <span class="review-strip__text">
            <span class="review-strip__name">{{ localizedText(station.name, locale) }}</span>
            <span class="review-strip__meta">
              <bdi>{{ station.scored }} / {{ station.scorable }}</bdi>
              · {{ t(`review.strip.states.${station.state}`) }}
            </span>
          </span>
        </button>
      </li>
    </ol>
  </nav>
</template>
