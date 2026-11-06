<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import GapItemCard from './GapItemCard.vue'
import InsightPercent from './InsightPercent.vue'
import { GAP_CLASSES, GAP_ICONS, GAP_TONES, STRENGTHS_PREVIEW_COUNT } from '@/constants/insights'
import { itemKey, previewOf } from '@/utils/insights'

const props = defineProps({
  id: { type: String, required: true },
  gapClass: { type: String, required: true },
  items: { type: Array, required: true },
  rules: { type: Object, required: true },
  current: { type: Boolean, default: false },
  labelledBy: { type: String, default: '' },
})

const { t, n } = useI18n()
const expanded = ref(false)
const visible = computed(() => previewOf(props.items, expanded.value, STRENGTHS_PREVIEW_COUNT))
const hidden = computed(() => props.items.length - STRENGTHS_PREVIEW_COUNT)
const titleId = computed(() => `${props.id}-title`)
const rangeKey = computed(
  () =>
    ({
      [GAP_CLASSES.STRENGTH]: 'from',
      [GAP_CLASSES.CRITICAL_GAP]: 'below',
      [GAP_CLASSES.OPPORTUNITY]: 'between',
    })[props.gapClass],
)
</script>

<template>
  <section
    :id="id"
    class="gap-column"
    :class="[`gap-column--${GAP_TONES[gapClass]}`, { 'is-current': current }]"
    :aria-labelledby="labelledBy || titleId"
    :role="labelledBy ? 'tabpanel' : undefined"
    :data-class="gapClass"
    data-testid="gap-column"
  >
    <header class="gap-column__header">
      <AppIcon :name="GAP_ICONS[gapClass]" class="gap-column__icon" />
      <div class="gap-column__titles">
        <h3 :id="titleId" class="gap-column__title">
          {{ t(`insights.strengths.columns.${gapClass}`) }}
        </h3>
        <i18n-t :keypath="`insights.strengths.range.${rangeKey}`" tag="p" class="gap-column__range">
          <template #value>
            <InsightPercent
              :value="gapClass === GAP_CLASSES.STRENGTH ? rules.strengthFrom : rules.criticalBelow"
            />
          </template>
          <template #min><InsightPercent :value="rules.criticalBelow" /></template>
          <template #max><InsightPercent :value="rules.strengthFrom" /></template>
        </i18n-t>
      </div>
      <span class="gap-column__count">
        <bdi dir="ltr">{{ n(items.length) }}</bdi>
      </span>
    </header>
    <p v-if="!items.length" class="gap-column__empty">
      {{ t(`insights.strengths.emptyColumn.${gapClass}`) }}
    </p>
    <ul v-else class="gap-column__list">
      <li v-for="item in visible" :key="itemKey(item)">
        <GapItemCard :item="item" />
      </li>
    </ul>
    <button
      v-if="hidden > 0"
      type="button"
      class="gap-column__more"
      :aria-expanded="expanded"
      @click="expanded = !expanded"
    >
      <AppIcon :name="expanded ? 'chevron-up' : 'chevron-down'" />
      {{
        expanded
          ? t('insights.strengths.showLess')
          : t('insights.strengths.showMore', { count: n(hidden) }, hidden)
      }}
    </button>
  </section>
</template>
