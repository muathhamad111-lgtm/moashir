<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import { FILTERING_OUTCOMES } from '@/constants/advanced'

defineProps({
  decisions: { type: Array, required: true },
})

const { t } = useI18n()
</script>

<template>
  <ol v-if="decisions.length" class="filtering-decisions">
    <li v-for="decision in decisions" :key="decision.id" class="filtering-decisions__item">
      <div class="filtering-decisions__head">
        <Badge :variant="decision.outcome === FILTERING_OUTCOMES.QUALIFIED ? 'success' : 'danger'">{{ t(`filtering.outcomes.${decision.outcome}`) }}</Badge>
        <Badge v-if="decision.isOverride" variant="warning">{{ t('filtering.override.badge') }}</Badge>
        <DateStamp :value="decision.decidedAt" />
      </div>
      <p class="filtering-decisions__meta">
        {{ t('filtering.decisions.figures', { initial: decision.initialPercent, threshold: decision.thresholdPercent }) }}
        <span v-if="decision.decidedBy?.name">— {{ decision.decidedBy.name }}</span>
      </p>
      <p v-if="decision.reason" class="filtering-decisions__reason"><strong>{{ t('filtering.decisions.reason') }}</strong> {{ decision.reason }}</p>
      <p v-if="decision.note" class="filtering-decisions__note"><strong>{{ t('filtering.decisions.note') }}</strong> {{ decision.note }}</p>
    </li>
  </ol>
  <p v-else class="filtering-decisions__empty">{{ t('filtering.decisions.empty') }}</p>
</template>
