<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePortalIdentity } from '@/composables/usePortalIdentity'
import { percentText } from '@/utils/percentText'
import BrandPattern from '@/components/ui/BrandPattern.vue'

const props = defineProps({
  journey: { type: Object, default: null },
})

const { t, n } = useI18n()
const { firstName } = usePortalIdentity()

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
  <section class="journey-hero" aria-labelledby="journey-hero-title">
    <span class="journey-hero__pattern"><BrandPattern variant="lattice" /></span>
    <div class="journey-hero__inner">
      <p class="journey-hero__eyebrow">{{ t('portal.home.hero.eyebrow') }}</p>
      <h1 id="journey-hero-title" class="journey-hero__title">
        {{ t('portal.home.hero.title', { name: firstName }) }}
      </h1>
      <i18n-t v-if="leadKey" :keypath="leadKey" scope="global" tag="p" class="journey-hero__lead">
        <template #percent>
          <bdi dir="ltr">{{ percentText(n, t, selfPercent) }}</bdi>
        </template>
        <template #station>{{ stationName }}</template>
      </i18n-t>
    </div>
  </section>
</template>
