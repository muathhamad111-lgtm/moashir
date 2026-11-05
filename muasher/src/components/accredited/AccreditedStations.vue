<script setup>
import { useI18n } from 'vue-i18n'
import { STATION_STATES } from '@/utils/accredited/submissionProgress'
import { formatNumber, formatPadded } from '@/utils/formatNumber'
import { localizedText } from '@/utils/localizedText'
import AppIcon from '@/components/ui/AppIcon.vue'

const LEGEND = [STATION_STATES.COMPLETE, STATION_STATES.PARTIAL, STATION_STATES.EMPTY]

defineProps({
  stations: { type: Array, required: true },
  activeId: { type: Number, default: null },
})

const emit = defineEmits(['select'])
const { t, n, locale } = useI18n()
</script>

<template>
  <section class="station-row" aria-labelledby="station-row-title">
    <div class="station-row__head">
      <h2 id="station-row-title" class="station-row__title">
        {{ t('portal.assessments.task.stations', { count: stations.length }, stations.length) }}
      </h2>
      <ul class="station-row__legend">
        <li v-for="state in LEGEND" :key="state" :class="`station-row__legend-item--${state}`">
          {{ t(`portal.assessments.task.states.${state}`) }}
        </li>
      </ul>
    </div>
    <ol class="station-row__list">
      <li v-for="(station, index) in stations" :key="station.id" class="station-row__item">
        <button
          type="button"
          class="station-row__button"
          :class="[
            `station-row__button--${station.state}`,
            { 'is-active': station.id === activeId },
          ]"
          :aria-current="station.id === activeId ? 'step' : undefined"
          @click="emit('select', station.id)"
        >
          <span class="station-row__marker" aria-hidden="true">
            <AppIcon
              v-if="station.state === STATION_STATES.COMPLETE && station.id !== activeId"
              name="check"
            />
            <bdi v-else dir="ltr">{{ formatPadded(n, index + 1) }}</bdi>
          </span>
          <span class="station-row__name">{{ localizedText(station.name, locale) }}</span>
          <bdi class="station-row__count" dir="ltr">{{
            t('portal.home.range', {
              done: formatNumber(n, station.complete),
              total: formatNumber(n, station.total),
            })
          }}</bdi>
          <span class="station-row__sr">{{
            t(`portal.assessments.task.states.${station.state}`)
          }}</span>
        </button>
      </li>
    </ol>
  </section>
</template>
