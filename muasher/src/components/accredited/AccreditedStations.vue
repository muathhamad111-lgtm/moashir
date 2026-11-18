<script setup>
import { nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { STATION_STATES } from '@/utils/accredited/submissionProgress'
import { formatNumber, formatPadded } from '@/utils/formatNumber'
import { localizedText } from '@/utils/localizedText'
import AppIcon from '@/components/ui/AppIcon.vue'

const LEGEND = [STATION_STATES.COMPLETE, STATION_STATES.PARTIAL, STATION_STATES.EMPTY]
const PERCENT = 100
const CELEBRATION_MS = 1200

const props = defineProps({
  stations: { type: Array, required: true },
  activeId: { type: Number, default: null },
  // Slim single row for the assessor screen: no title card or legend, names as tooltips.
  compact: { type: Boolean, default: false },
})

const emit = defineEmits(['select'])
const { t, n, locale } = useI18n()

// A station that turns complete while the assessor works plays a short celebration once.
const celebrating = ref(new Set())
watch(
  () => props.stations.map((station) => [station.id, station.state]),
  (current, previous) => {
    if (!props.compact || !previous) return
    const before = new Map(previous)
    current
      .filter(([id, state]) => state === STATION_STATES.COMPLETE && before.get(id) !== state)
      .forEach(([id]) => celebrate(id))
  },
)

function celebrate(id) {
  celebrating.value = new Set([...celebrating.value, id])
  setTimeout(() => {
    const next = new Set(celebrating.value)
    next.delete(id)
    celebrating.value = next
  }, CELEBRATION_MS)
}

// The line after a station: green once the station is complete, brand colour up to the current one.
function segmentClasses(station, index) {
  if (!props.compact) return undefined
  const activeIndex = props.stations.findIndex((item) => item.id === props.activeId)
  return {
    'station-row__item--done': station.state === STATION_STATES.COMPLETE,
    'station-row__item--reached': index < activeIndex,
  }
}

// On a narrow screen the compact row scrolls; keep the current criterion in view without moving
// the page when the row itself is off screen.
const list = ref(null)
watch(
  () => props.activeId,
  async () => {
    if (!props.compact) return
    await nextTick()
    const row = list.value
    if (!row || row.scrollWidth <= row.clientWidth) return
    const { top, bottom } = row.getBoundingClientRect()
    if (bottom < 0 || top > window.innerHeight) return
    row
      .querySelector('[aria-current="step"]')
      ?.scrollIntoView?.({ block: 'nearest', inline: 'center', behavior: 'smooth' })
  },
  { immediate: true },
)

// The compact row shows the check on a complete station even while it is the current one.
function showsCheck(station) {
  if (station.state !== STATION_STATES.COMPLETE) return false
  return props.compact || station.id !== props.activeId
}

// Drives the partial fill of the compact marker: scored items when known, else complete subs.
function progressStyle(station) {
  const [done, total] = station.scorable
    ? [station.scored, station.scorable]
    : [station.complete, station.total]
  if (!props.compact || !total) return undefined
  return { '--station-progress': `${Math.round((done / total) * PERCENT)}%` }
}
</script>

<template>
  <section
    class="station-row"
    :class="{ 'station-row--compact': compact }"
    aria-labelledby="station-row-title"
  >
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
    <ol ref="list" class="station-row__list">
      <li
        v-for="(station, index) in stations"
        :key="station.id"
        class="station-row__item"
        :class="segmentClasses(station, index)"
      >
        <button
          type="button"
          class="station-row__button"
          :class="[
            `station-row__button--${station.state}`,
            { 'is-active': station.id === activeId, 'is-celebrating': celebrating.has(station.id) },
          ]"
          :aria-current="station.id === activeId ? 'step' : undefined"
          :title="compact ? localizedText(station.name, locale) : undefined"
          @click="emit('select', station.id)"
        >
          <span class="station-row__marker" aria-hidden="true" :style="progressStyle(station)">
            <AppIcon v-if="showsCheck(station)" name="check" />
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
