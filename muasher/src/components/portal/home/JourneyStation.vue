<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'
import { percentText } from '@/utils/percentText'
import { riyadhDateKey } from '@/utils/datetime'
import { STATION_STATES } from '@/composables/useEntityJourney'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  station: { type: Object, required: true },
  number: { type: Number, required: true },
})

const { t, n } = useI18n()

const metaKey = computed(
  () => `portal.home.stations.${props.station.key}.meta.${props.station.meta.key}`,
)
const params = computed(() => props.station.meta.params ?? {})
const stateLabel = computed(() => t(`portal.home.stationStates.${props.station.state}`))
</script>

<template>
  <li
    class="journey-station"
    :class="`journey-station--${station.state}`"
    :aria-current="station.state === STATION_STATES.CURRENT ? 'step' : undefined"
  >
    <span class="journey-station__node" aria-hidden="true">
      <AppIcon v-if="station.state === STATION_STATES.DONE" name="check" />
      <span v-else>{{ formatNumber(n, number) }}</span>
    </span>
    <span class="journey-station__body">
      <span class="journey-station__title">
        {{ t(`portal.home.stations.${station.key}.title`) }}
        <span class="journey-station__state">{{ stateLabel }}</span>
      </span>
      <i18n-t :keypath="metaKey" scope="global" tag="span" class="journey-station__meta">
        <template #date>
          <bdi dir="ltr">{{ params.date ? riyadhDateKey(params.date) : '' }}</bdi>
        </template>
        <template #percent>
          <bdi dir="ltr">{{ percentText(n, t, params.percent) }}</bdi>
        </template>
        <template #progress>
          <bdi dir="ltr">{{
            t('portal.home.range', {
              done: formatNumber(n, params.done),
              total: formatNumber(n, params.total),
            })
          }}</bdi>
        </template>
        <template #number>
          <bdi dir="ltr">{{ params.number }}</bdi>
        </template>
      </i18n-t>
    </span>
  </li>
</template>
