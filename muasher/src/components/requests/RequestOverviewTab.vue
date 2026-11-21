<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import CompletionRequestCard from '@/components/accredited/CompletionRequestCard.vue'
import RequestPaymentCard from './RequestPaymentCard.vue'
import RequestCertificateCard from './RequestCertificateCard.vue'
import RequestResultBreakdown from './RequestResultBreakdown.vue'
import { ACCREDITED_STATUSES, COMPLETION_STATUSES } from '@/constants/accredited'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { formatNumber } from '@/utils/formatNumber'

// The request at a glance: its result once completed, the open completion request, the payment and the certificate.
const props = defineProps({
  detail: { type: Object, required: true },
  receiptDownloading: { type: Boolean, default: false },
})

const emit = defineEmits(['download-receipt'])
const { t, n } = useI18n()
const isAccredited = computed(() => props.detail.path === ASSESSMENT_PATHS.ACCREDITED)
const isCompleted = computed(() => props.detail.status === ACCREDITED_STATUSES.COMPLETED)
const result = computed(() => (isCompleted.value ? props.detail.record.result : null))
const openCompletion = computed(() =>
  props.detail.completionRequests.find(
    (completion) => completion.status === COMPLETION_STATUSES.OPEN,
  ),
)
</script>

<template>
  <div class="request-overview">
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
    <RequestResultBreakdown v-if="result && isAccredited" :assessment-id="detail.id" />
    <Card v-if="openCompletion">
      <CompletionRequestCard :request="openCompletion" show-status />
    </Card>
    <RequestPaymentCard
      v-if="detail.payment.details"
      :payment="detail.payment.details"
      :downloading="receiptDownloading"
      @download="emit('download-receipt', $event)"
    />
    <RequestCertificateCard
      v-if="isAccredited"
      :key="`${detail.id}-${detail.status}`"
      :request-id="detail.id"
      :completed="isCompleted"
    />
  </div>
</template>
