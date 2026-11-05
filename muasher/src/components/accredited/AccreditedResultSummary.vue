<script setup>
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import DateStamp from './DateStamp.vue'

defineProps({
  result: { type: Object, required: true },
})

const { t } = useI18n()
</script>

<template>
  <Card class="accredited-result-summary">
    <div class="accredited-result-summary__score">
      <div
        class="accredited-result-summary__gauge"
        role="img"
        :aria-label="t('accredited.result.percentAria', { percent: result.percent })"
      >
        <span class="accredited-result-summary__value u-ltr">{{ result.percent }}%</span>
      </div>
      <div class="accredited-result-summary__detail">
        <p class="accredited-result-summary__total">
          {{ t('accredited.result.totalOf', { total: result.total, max: result.maxTotal }) }}
        </p>
        <p class="accredited-result-summary__meta">
          {{ t('accredited.result.completedAt') }}
          <DateStamp :value="result.completedAt" with-hijri />
        </p>
      </div>
    </div>
    <p class="accredited-result-summary__confidential" role="note">
      <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
      {{ t('accredited.result.confidential') }}
    </p>
  </Card>
</template>
