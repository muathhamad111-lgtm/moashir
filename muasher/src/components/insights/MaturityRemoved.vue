<script setup>
import { useI18n } from 'vue-i18n'
import InsightPercent from './InsightPercent.vue'
import { localizedText } from '@/utils/localizedText'

defineProps({
  items: { type: Array, required: true },
})

const { t, locale } = useI18n()
</script>

<template>
  <section class="b-card maturity-removed" data-testid="maturity-removed">
    <h3 class="b-card__title">{{ t('insights.delta.removed') }}</h3>
    <ul class="maturity-removed__list">
      <li v-for="item in items" :key="item.code" class="maturity-removed__item">
        <span>
          <bdi dir="ltr">{{ item.code }}</bdi> · {{ localizedText(item.name, locale) }}
        </span>
        <i18n-t keypath="insights.maturity.removedRow" tag="span" class="maturity-removed__note">
          <template #percent><InsightPercent :value="item.previousPercent" /></template>
        </i18n-t>
      </li>
    </ul>
  </section>
</template>
