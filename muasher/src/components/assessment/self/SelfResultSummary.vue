<script setup>
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'

defineProps({
  result: { type: Object, required: true },
  ownerView: { type: Boolean, default: true },
})

const { t } = useI18n()
</script>

<template>
  <div class="self-result__summary">
    <Card>
      <div class="self-result__score">
        <div class="self-result__gauge" role="img" :aria-label="t('assessment.self.result.percentAria', { percent: result.percent })">
          <span class="self-result__gauge-value u-ltr">{{ result.percent }}%</span>
        </div>
        <div class="self-result__score-detail">
          <p class="self-result__score-total">
            {{ t('assessment.self.result.totalOf', { total: result.total, max: result.maxTotal }) }}
          </p>
          <p class="self-result__score-hint">{{ t('assessment.self.result.totalHint') }}</p>
        </div>
      </div>
    </Card>
    <div class="self-result__flags" role="note">
      <p v-if="ownerView" class="self-result__flag">
        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
        {{ t('assessment.self.result.savedToHistory') }}
      </p>
      <p class="self-result__flag self-result__flag--muted">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        {{ t('assessment.self.result.exclusionNote') }}
      </p>
    </div>
  </div>
</template>
