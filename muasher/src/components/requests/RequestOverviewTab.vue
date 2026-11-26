<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import RequestFacts from './RequestFacts.vue'
import { ACCREDITED_STATUSES } from '@/constants/accredited'
import { formatNumber } from '@/utils/formatNumber'

// The request at a glance: its data for this path and state. The payment, the certificate and the completion
// requests have their own tabs (operator review 2026-11-25); a completed request without a level keeps its result here.
const props = defineProps({
  detail: { type: Object, required: true },
})

const { t, n } = useI18n()
const result = computed(() =>
  props.detail.status === ACCREDITED_STATUSES.COMPLETED && !props.detail.record.level
    ? props.detail.record.result
    : null,
)
</script>

<template>
  <div class="request-overview">
    <Card class="request-overview__data">
      <template #header>
        <h2 class="card__title-text">{{ t('requests.detail.requestInfo') }}</h2>
      </template>
      <RequestFacts :detail="detail" />
    </Card>
    <Card v-if="result" class="request-result" data-testid="request-result">
      <template #header>
        <h2 class="card__title-text">{{ t('requests.detail.result.title') }}</h2>
      </template>
      <p class="request-result__percent">
        {{ t('requests.detail.result.percent', { value: formatNumber(n, result.percent) }) }}
      </p>
      <p v-if="result.maxTotal" class="request-result__total">
        {{
          t('requests.detail.result.total', {
            total: formatNumber(n, result.total),
            max: formatNumber(n, result.maxTotal),
          })
        }}
      </p>
    </Card>
  </div>
</template>
