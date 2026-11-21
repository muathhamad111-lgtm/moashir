<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { localizedText } from '@/utils/localizedText'
import { toCanonicalStatus } from '@/utils/canonicalStatus'
import PathHeaderCard from './PathHeaderCard.vue'

const props = defineProps({
  accredited: { type: Object, required: true },
})

const { t, locale } = useI18n()
const period = computed(() => props.accredited.currentPeriod)
const next = computed(() => props.accredited.nextPeriod)
const eyebrow = computed(() =>
  props.accredited.cadence
    ? t('paths.pages.accredited.currentPeriod', {
        cadence: t(`paths.cadence.${props.accredited.cadence}`),
      })
    : t('paths.pages.accredited.period'),
)
</script>

<template>
  <PathHeaderCard
    :eyebrow="eyebrow"
    :title="period ? localizedText(period.label, locale) : t('paths.pages.accredited.notLive')"
    test-id="path-period-card"
  >
    <p v-if="period" class="path-header-card__text">
      <bdi dir="ltr">{{ period.startsOn }}</bdi>
      –
      <bdi dir="ltr">{{ period.endsOn }}</bdi>
    </p>
    <p
      v-if="accredited.periodStatus"
      class="path-period-chip"
      :class="`path-period-chip--${accredited.periodStatus}`"
      data-testid="path-period-status"
    >
      {{ t(`paths.periodStatus.${toCanonicalStatus(accredited.periodStatus)}`) }}
    </p>
    <p v-if="next" class="path-header-card__note path-header-card__note--groups">
      <span class="path-header-card__group">{{
        t('paths.pages.accredited.nextPeriodLabel', { period: localizedText(next.label, locale) })
      }}</span>
      <i18n-t
        keypath="paths.pages.accredited.noticeOn"
        scope="global"
        tag="span"
        class="path-header-card__group"
      >
        <template #date>
          <bdi dir="ltr">{{ next.noticeOn ?? next.startsOn }}</bdi>
        </template>
      </i18n-t>
    </p>
  </PathHeaderCard>
</template>
