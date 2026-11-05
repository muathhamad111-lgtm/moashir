<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  row: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const { n } = useI18n()

function pct(value) {
  const weight = Number(props.row.weight) || 0
  if (!weight) return 0
  return Math.min(100, Math.max(0, Math.round((Number(value) / weight) * 100)))
}
const beforePct = computed(() => pct(props.row.before))
const afterPct = computed(() => pct(props.row.after))

const diffLabel = computed(() => {
  const diff = Number(props.row.diff) || 0
  const magnitude = n(Math.abs(diff), 'default')
  if (diff > 0) return `+${magnitude}`
  if (diff < 0) return `−${magnitude}`
  return n(0, 'default')
})
const diffClass = computed(() => {
  const diff = Number(props.row.diff) || 0
  if (diff > 0) return 'reassessment-row__diff--up'
  if (diff < 0) return 'reassessment-row__diff--down'
  return 'reassessment-row__diff--flat'
})
</script>

<template>
  <div class="reassessment-row">
    <div class="reassessment-row__head">
      <div class="reassessment-row__titles">
        <span class="reassessment-row__main">{{ localized(row.mainName) }}</span>
        <h3 class="reassessment-row__sub">{{ localized(row.subName) }}</h3>
      </div>
      <div class="reassessment-row__badges">
        <span v-if="row.inKitScope" class="reassessment-row__scope">{{
          $t('bag.comparison.inKit')
        }}</span>
        <StatusBadge :status="row.outcome" />
      </div>
    </div>

    <div class="reassessment-row__scores">
      <div class="reassessment-row__score">
        <span class="reassessment-row__score-label">{{ $t('bag.comparison.before') }}</span>
        <span class="reassessment-row__score-value">{{ n(row.before, 'default') }}</span>
      </div>
      <AppIcon name="arrow-right" class="reassessment-row__arrow" />
      <div class="reassessment-row__score">
        <span class="reassessment-row__score-label">{{ $t('bag.comparison.after') }}</span>
        <span class="reassessment-row__score-value">{{ n(row.after, 'default') }}</span>
      </div>
      <div class="reassessment-row__score reassessment-row__score--diff">
        <span class="reassessment-row__score-label">{{ $t('bag.comparison.diff') }}</span>
        <span class="reassessment-row__diff" :class="diffClass">{{ diffLabel }}</span>
      </div>
    </div>

    <div class="reassessment-row__bars">
      <div class="reassessment-row__bar" role="presentation">
        <span
          class="reassessment-row__bar-fill reassessment-row__bar-fill--before"
          :style="{ '--bar-fill': `${beforePct}%` }"
        ></span>
      </div>
      <div class="reassessment-row__bar" role="presentation">
        <span
          class="reassessment-row__bar-fill reassessment-row__bar-fill--after"
          :style="{ '--bar-fill': `${afterPct}%` }"
        ></span>
      </div>
    </div>

    <p class="reassessment-row__caption">
      {{
        $t('bag.comparison.remaining', {
          gap: n(row.gapAfter, 'default'),
          target: n(row.weight, 'default'),
        })
      }}
    </p>
  </div>
</template>
