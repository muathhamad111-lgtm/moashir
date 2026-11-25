<script setup>
import { useI18n } from 'vue-i18n'

// The entity's self and advanced results beside a BAG request, as context for its evaluator.
defineProps({
  selfScorePercent: { type: Number, default: null },
  advancedScorePercent: { type: Number, default: null },
})

const { t, n } = useI18n()
</script>

<template>
  <span class="bag-requests__context">
    <span v-if="selfScorePercent != null" class="bag-requests__context-chip">
      {{ t('bagRequests.contextSelf', { score: n(selfScorePercent, 'default') }) }}
    </span>
    <span v-if="advancedScorePercent != null" class="bag-requests__context-chip">
      {{ t('bagRequests.contextAdvanced', { score: n(advancedScorePercent, 'default') }) }}
    </span>
    <span v-if="selfScorePercent == null && advancedScorePercent == null">—</span>
  </span>
</template>
