<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import InsightPercent from './InsightPercent.vue'
import { GAP_COLUMNS, GAP_ICONS, GAP_TONES } from '@/constants/insights'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  counts: { type: Object, required: true },
  mains: { type: Array, required: true },
})

const { t, n, locale } = useI18n()
const total = computed(() =>
  GAP_COLUMNS.reduce((sum, column) => sum + (props.counts[column.countKey] ?? 0), 0),
)
</script>

<template>
  <section class="b-card sg-summary" :aria-label="t('insights.strengths.summaryLabel')">
    <ul class="sg-summary__tiles">
      <li
        v-for="column in GAP_COLUMNS"
        :key="column.gapClass"
        class="sg-summary__tile"
        :class="`sg-summary__tile--${GAP_TONES[column.gapClass]}`"
        :data-class="column.gapClass"
      >
        <span class="sg-summary__label">
          <AppIcon :name="GAP_ICONS[column.gapClass]" />
          {{ t(`insights.strengths.columns.${column.gapClass}`) }}
        </span>
        <span class="sg-summary__value">
          <bdi dir="ltr" class="sg-summary__number">{{ n(counts[column.countKey] ?? 0) }}</bdi>
          <span class="sg-summary__unit">
            {{ t('insights.strengths.subUnit', counts[column.countKey] ?? 0) }}
          </span>
        </span>
      </li>
      <li class="sg-summary__tile sg-summary__tile--total" data-testid="sg-total">
        <span class="sg-summary__label">{{ t('insights.strengths.total') }}</span>
        <span class="sg-summary__value">
          <bdi dir="ltr" class="sg-summary__number">{{ n(total) }}</bdi>
          <span class="sg-summary__unit">
            {{ t('insights.strengths.subUnit', total) }} · {{ t('insights.strengths.totalNote') }}
          </span>
        </span>
      </li>
    </ul>
    <h3 class="sg-summary__mains-title">{{ t('insights.strengths.mainsTitle') }}</h3>
    <ul class="sg-summary__mains">
      <li
        v-for="main in mains"
        :key="main.code"
        class="sg-summary__main"
        :class="`sg-summary__main--${GAP_TONES[main.class]}`"
      >
        <span class="sg-summary__dot" aria-hidden="true"></span>
        <span
          >{{ localizedText(main.name, locale) }} · <InsightPercent :value="main.percent"
        /></span>
        <span class="insights-sr">{{ t(`insights.classes.${main.class}`) }}</span>
      </li>
    </ul>
  </section>
</template>
