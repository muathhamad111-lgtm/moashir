<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { localizedText } from '@/utils/localizedText'
import { isoDate } from '@/utils/datetime'
import PathHeaderCard from './PathHeaderCard.vue'

const props = defineProps({
  award: { type: Object, required: true },
})

const { t, locale } = useI18n()
const cycle = computed(() => props.award.cycle)
const regions = computed(() =>
  (cycle.value?.regions ?? []).map((region) => localizedText(region.name, locale.value)).join('، '),
)
const nextStart = computed(() => isoDate(props.award.nextCycle?.submissionStart ?? null))
</script>

<template>
  <PathHeaderCard
    :eyebrow="t(`paths.pages.award.window.${award.windowState}`)"
    :title="cycle ? localizedText(cycle.name, locale) : t('paths.pages.award.noCycle')"
    test-id="path-cycle-card"
  >
    <template v-if="cycle">
      <p class="path-header-card__text">
        <bdi dir="ltr">{{ isoDate(cycle.submissionStart) }}</bdi>
        –
        <bdi dir="ltr">{{ isoDate(cycle.submissionEnd) }}</bdi>
      </p>
      <p v-if="regions" class="path-header-card__note">
        {{ t('paths.pages.award.regions', { regions }) }}
      </p>
      <p v-if="cycle.minEntityAgeYears" class="path-header-card__note">
        {{ t('paths.pages.award.minAge', { years: cycle.minEntityAgeYears }) }}
      </p>
      <p class="path-header-card__note">{{ t('paths.pages.award.resultsNote') }}</p>
    </template>
    <p v-if="nextStart" class="path-header-card__note" data-testid="path-next-cycle">
      <i18n-t keypath="paths.pages.award.nextCycle" scope="global">
        <template #date>
          <bdi dir="ltr">{{ nextStart }}</bdi>
        </template>
      </i18n-t>
    </p>
  </PathHeaderCard>
</template>
