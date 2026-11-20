<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCriteria } from '@/composables/useCriteria'
import { formatShare, shareOf, sumWeights, weightBudget } from '@/utils/weight'

// How a parent's weight is split over its active children, with the missing part shown as a gap.
const props = defineProps({
  parentWeight: { type: String, required: true },
  items: { type: Array, required: true },
})

const { t, locale } = useI18n()
const { weight } = useCriteria()

const SWATCHES = 4

const active = computed(() => props.items.filter((item) => item.active !== false))
const budget = computed(() =>
  weightBudget(props.parentWeight, sumWeights(active.value.map((item) => item.weight))),
)
const segments = computed(() =>
  active.value.map((item, position) => ({
    ...item,
    share: shareOf(item.weight, props.parentWeight),
    swatch: position % SWATCHES,
  })),
)
const gapShare = computed(() =>
  budget.value.state === 'under' ? shareOf(budget.value.remaining, props.parentWeight) : 0,
)
const share = (value) => formatShare(value, locale.value)
</script>

<template>
  <div class="criteria-split" :class="{ 'is-over': budget.state === 'over' }">
    <div class="criteria-split__bar" aria-hidden="true">
      <span
        v-for="segment in segments"
        :key="segment.key"
        class="criteria-split__segment"
        :class="`criteria-split__segment--${segment.swatch}`"
        :style="{ inlineSize: `${segment.share}%` }"
      ></span>
      <span
        v-if="gapShare"
        class="criteria-split__segment criteria-split__segment--gap"
        :style="{ inlineSize: `${gapShare}%` }"
      ></span>
    </div>
    <ul class="criteria-split__legend" :aria-label="t('criteria.workspace.board.splitLabel')">
      <li v-for="segment in segments" :key="segment.key" class="criteria-split__legend-item">
        <span
          class="criteria-split__swatch"
          :class="`criteria-split__segment--${segment.swatch}`"
          aria-hidden="true"
        ></span>
        <bdi>{{ segment.code }}</bdi>
        <bdi dir="ltr">{{ share(segment.share) }}</bdi>
      </li>
      <li v-if="gapShare" class="criteria-split__legend-item criteria-split__legend-item--gap">
        <span class="criteria-split__swatch criteria-split__segment--gap" aria-hidden="true"></span>
        {{ t('criteria.workspace.board.missing', { weight: weight(budget.remaining) }) }}
      </li>
      <li
        v-if="budget.state === 'over'"
        class="criteria-split__legend-item criteria-split__legend-item--gap"
      >
        {{
          t('criteria.workspace.board.over', { weight: weight(budget.remaining.replace('-', '')) })
        }}
      </li>
    </ul>
  </div>
</template>
