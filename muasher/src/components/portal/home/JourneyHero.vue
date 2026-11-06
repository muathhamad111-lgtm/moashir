<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePortalIdentity } from '@/composables/usePortalIdentity'
import { percentText } from '@/utils/percentText'

const props = defineProps({
  journey: { type: Object, default: null },
})

const { t, n } = useI18n()
const { firstName, entityName } = usePortalIdentity()

const stationName = computed(() =>
  props.journey ? t(`portal.home.stations.${props.journey.currentStation.key}.title`) : '',
)
const selfPercent = computed(() => props.journey?.selfResult?.result?.percent ?? null)
const leadKey = computed(() => {
  if (!props.journey) return ''
  if (props.journey.isComplete) return 'portal.home.hero.leadComplete'
  return selfPercent.value === null ? 'portal.home.hero.lead' : 'portal.home.hero.leadWithResult'
})
</script>

<template>
  <header class="journey-hero">
    <p class="journey-hero__greeting">{{ t('portal.home.hero.title', { name: firstName }) }}</p>
    <h1 class="journey-hero__title">{{ entityName }}</h1>
    <i18n-t v-if="leadKey" :keypath="leadKey" scope="global" tag="p" class="journey-hero__lead">
      <template #percent>
        <bdi dir="ltr">{{ percentText(n, t, selfPercent) }}</bdi>
      </template>
      <template #station>{{ stationName }}</template>
    </i18n-t>
  </header>
</template>
