<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRegions } from '@/composables/useRegions'
import { localizedText } from '@/utils/localizedText'

// The applied filters under the title, so a printed or shared board says what it shows.
const props = defineProps({
  state: { type: Object, required: true },
})

const { t, locale } = useI18n()
const { regions, load } = useRegions()
load()

const LABEL_PREFIX = Object.freeze({
  path: 'dashboard.overview.pathShort',
  type: 'entities.enums.entityType',
  sector: 'entities.enums.developmentSector',
  size: 'entities.enums.orgSize',
})

function valueText(field, value) {
  if (field !== 'region') return t(`${LABEL_PREFIX[field]}.${value}`)
  const region = regions.value.find((item) => item.code === value)
  return region ? localizedText(region.name, locale.value) : value
}

const chips = computed(() => [
  {
    key: 'period',
    label: t('dashboard.board.filters.period'),
    value: t(`dashboard.overview.period.presets.${props.state.period.value}`),
  },
  ...Object.entries(props.state.filters.request.value).map(([field, value]) => ({
    key: field,
    label: t(`dashboard.board.filters.${field}`),
    value: valueText(field, value),
  })),
])
</script>

<template>
  <ul class="board-head__chips" :aria-label="t('dashboard.board.filters.applied')">
    <li v-for="chip in chips" :key="chip.key" class="board-head__chip" :data-chip="chip.key">
      <span>{{ chip.label }}:</span>{{ ' ' }}<strong>{{ chip.value }}</strong>
    </li>
  </ul>
</template>
