<script setup>
import { useI18n } from 'vue-i18n'
import { CERTIFICATE_SEALS } from '@/constants/seals'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  ranges: { type: Array, required: true },
})

const { t } = useI18n()
</script>

<template>
  <section class="level-ranges" aria-labelledby="level-ranges-title">
    <h2 id="level-ranges-title" class="level-ranges__title">
      {{ t('settings.certificates.rangesTitle') }}
    </h2>
    <p v-if="!ranges.length" class="level-ranges__empty" role="status">
      {{ t('settings.certificates.rangesInvalid') }}
    </p>
    <ul v-else class="level-ranges__list">
      <li class="level-ranges__item level-ranges__item--participation">
        <span class="level-ranges__seal level-ranges__seal--none" aria-hidden="true">
          <AppIcon name="certificate" />
        </span>
        <span class="level-ranges__name">{{ t('settings.certificates.participation') }}</span>
        <span class="level-ranges__range">
          {{ t('settings.certificates.below') }} <bdi dir="ltr">{{ ranges[0].from }}%</bdi>
        </span>
      </li>
      <li v-for="range in ranges" :key="range.level" class="level-ranges__item">
        <img class="level-ranges__seal" :src="CERTIFICATE_SEALS[range.level]" alt="" />
        <span class="level-ranges__name">{{
          t(`settings.certificates.levels.${range.level}`)
        }}</span>
        <span class="level-ranges__range">
          <bdi dir="ltr">{{ range.from }}%</bdi>
          <template v-if="range.to">
            <span aria-hidden="true">–</span>
            <bdi dir="ltr">{{ range.to }}%</bdi>
          </template>
          <span v-else>{{ t('settings.certificates.andAbove') }}</span>
        </span>
      </li>
    </ul>
  </section>
</template>
