<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import Tabs from '@/components/ui/Tabs.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import CompletionRequestCard from '@/components/accredited/CompletionRequestCard.vue'
import RequestSummaryHeader from '@/components/requests/RequestSummaryHeader.vue'
import RequestResultRibbon from '@/components/requests/RequestResultRibbon.vue'
import RequestResultBreakdown from '@/components/requests/RequestResultBreakdown.vue'
import RequestOverviewTab from '@/components/requests/RequestOverviewTab.vue'
import RequestPaymentCard from '@/components/requests/RequestPaymentCard.vue'
import RequestCertificateCard from '@/components/requests/RequestCertificateCard.vue'
import RequestLogTab from '@/components/requests/RequestLogTab.vue'
import RequestRecordDialogs from '@/components/requests/RequestRecordDialogs.vue'
import { useRequestDetail } from '@/composables/useRequestDetail'
import { useEvidenceBundleRequest } from '@/composables/useEvidenceBundle'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { financeService } from '@/services/financeService'
import { ACCREDITED_STATUSES } from '@/constants/accredited'
import { ASSESSMENT_PATHS } from '@/constants/assessment'

// The request record (operator choice 2026-11-26, layout A): the actions in the row above, one summary card (the
// identity with the status once at its end, then who holds the request and its dates), the result ribbon once
// completed, then one tab per section.
// Ordered by how often staff open them (operator review 2026-11-25): the request's data, then the work of the
// current state (the scores, the completion requests), then its documents (certificate, payment), the log last.
const TABS = Object.freeze({
  OVERVIEW: 'overview',
  ASSESSMENT: 'assessment',
  COMPLETION: 'completion',
  CERTIFICATE: 'certificate',
  PAYMENT: 'payment',
  LOG: 'log',
})
const TAB_QUERY = 'tab'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const requestId = computed(() => Number(route.params.id))
const { status, errorMessage, detail, assignmentMode, canReturn, canWithdraw, load, apply } =
  useRequestDetail(requestId)
const bundles = useEvidenceBundleRequest(requestId)
const receipts = useSignedDownload()
const dialogs = ref(null)

useHead({ title: () => detail.value?.number || t('requests.detail.title') })

const isCompleted = computed(() => detail.value?.status === ACCREDITED_STATUSES.COMPLETED)
const showsRibbon = computed(() => isCompleted.value && Boolean(detail.value?.record.level))
const showsBreakdown = computed(
  () => isCompleted.value && detail.value?.path === ASSESSMENT_PATHS.ACCREDITED,
)
const isAccredited = computed(() => detail.value?.path === ASSESSMENT_PATHS.ACCREDITED)
const completions = computed(() => [...(detail.value?.completionRequests ?? [])].reverse())
// A tab shows only when it applies: no payment on a free path or before any payment, no certificate before one
// is issued or the request completes.
const APPLIES = {
  [TABS.ASSESSMENT]: () => showsBreakdown.value,
  [TABS.COMPLETION]: () => completions.value.length > 0,
  [TABS.CERTIFICATE]: () =>
    isAccredited.value && (isCompleted.value || detail.value?.hasCertificates === true),
  [TABS.PAYMENT]: () => Boolean(detail.value?.payment.details),
}
const tabs = computed(() =>
  Object.values(TABS)
    .filter((value) => APPLIES[value]?.() ?? true)
    .map((value) => ({ value, label: t(`requests.detail.tabs.${value}`) })),
)
const tab = computed({
  get: () =>
    tabs.value.find((item) => item.value === route.query[TAB_QUERY])?.value ?? TABS.OVERVIEW,
  set: (value) => router.replace({ query: { ...route.query, [TAB_QUERY]: value } }),
})

function downloadReceipt(receiptId) {
  receipts.download('receipt', () => financeService.requestReceiptDownload(receiptId))
}

function openDialog(kind, mode) {
  dialogs.value?.open(kind, mode)
}

onMounted(load)
</script>

<template>
  <section class="request-detail">
    <div v-if="status === 'loading'" class="request-detail__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="5" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
    <EmptyState
      v-else-if="status === 'missing'"
      icon="folder"
      :title="t('requests.detail.missingTitle')"
      :message="t('requests.detail.missingMessage')"
    />
    <template v-else-if="detail">
      <RequestSummaryHeader
        :detail="detail"
        :assignment-mode="assignmentMode"
        :bundle-requesting="bundles.requesting.value"
        :can-return="canReturn"
        :can-withdraw="canWithdraw"
        :refresh-logo="() => load({ silent: true })"
        @assign="openDialog('assign', $event)"
        @bundle="bundles.requestBundle"
        @return="openDialog('return')"
        @withdraw="openDialog('withdraw')"
        @reopen="openDialog('reopen')"
        @force="openDialog('force')"
      />
      <RequestResultRibbon v-if="showsRibbon" :record="detail.record" />
      <Tabs v-model="tab" :tabs="tabs" class="request-detail__tabs">
        <template #default="{ active }">
          <RequestOverviewTab v-if="active === TABS.OVERVIEW" :detail="detail" />
          <div v-else-if="active === TABS.ASSESSMENT" class="request-detail__panel">
            <RequestResultBreakdown :assessment-id="detail.id" />
          </div>
          <div v-else-if="active === TABS.CERTIFICATE" class="request-detail__panel">
            <RequestCertificateCard
              :key="`${detail.id}-${detail.status}`"
              :request-id="detail.id"
              :completed="isCompleted"
            />
          </div>
          <div v-else-if="active === TABS.PAYMENT" class="request-detail__panel">
            <RequestPaymentCard
              :payment="detail.payment.details"
              :downloading="receipts.busy.receipt === true"
              @download="downloadReceipt"
            />
          </div>
          <div v-else-if="active === TABS.COMPLETION" class="request-detail__panel">
            <Card v-for="completion in completions" :key="completion.id">
              <CompletionRequestCard :request="completion" show-status />
            </Card>
          </div>
          <RequestLogTab v-else :detail="detail" />
        </template>
      </Tabs>
      <RequestRecordDialogs
        ref="dialogs"
        :detail="detail"
        @applied="apply"
        @changed="load({ silent: true })"
      />
    </template>
  </section>
</template>
