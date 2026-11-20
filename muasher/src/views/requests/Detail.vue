<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, RouterLink } from 'vue-router'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import Tabs from '@/components/ui/Tabs.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import CompletionRequestCard from '@/components/accredited/CompletionRequestCard.vue'
import PathBadge from '@/components/ui/PathBadge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import RequestFacts from '@/components/requests/RequestFacts.vue'
import RequestHeaderActions from '@/components/requests/RequestHeaderActions.vue'
import RequestOverviewTab from '@/components/requests/RequestOverviewTab.vue'
import RequestLogTab from '@/components/requests/RequestLogTab.vue'
import RequestRecordDialogs from '@/components/requests/RequestRecordDialogs.vue'
import { useRequestDetail } from '@/composables/useRequestDetail'
import { useEvidenceBundleRequest } from '@/composables/useEvidenceBundle'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { usePermissions } from '@/composables/usePermissions'
import { financeService } from '@/services/financeService'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'

// The request record (admin review 2026-11-20, approved design UX-B3): the number as the title with every
// action at the header's end, the request's data, then tabs instead of one long column.
const TABS = Object.freeze({ OVERVIEW: 'overview', COMPLETION: 'completion', LOG: 'log' })

const { t } = useI18n()
const route = useRoute()
const { can } = usePermissions()
const requestId = computed(() => Number(route.params.id))
const { status, errorMessage, detail, assignmentMode, canReturn, canWithdraw, load, apply } =
  useRequestDetail(requestId)
const bundles = useEvidenceBundleRequest(requestId)
const receipts = useSignedDownload()
const dialogs = ref(null)
const tab = ref(TABS.OVERVIEW)

useHead({ title: () => detail.value?.number || t('requests.detail.title') })

const backRoute = computed(() =>
  can(PERMISSIONS.REQUESTS_QUEUE_VIEW) ? ROUTE_NAMES.REQUESTS_INDEX : ROUTE_NAMES.REQUESTS_ASSIGNED,
)
const completions = computed(() => [...(detail.value?.completionRequests ?? [])].reverse())
const tabs = computed(() => [
  { value: TABS.OVERVIEW, label: t('requests.detail.tabs.overview') },
  ...(completions.value.length
    ? [{ value: TABS.COMPLETION, label: t('requests.detail.tabs.completion') }]
    : []),
  { value: TABS.LOG, label: t('requests.detail.tabs.log') },
])

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
    <header class="request-detail__header">
      <RouterLink class="request-detail__back" :to="{ name: backRoute }">
        <AppIcon name="arrow-left" />
        {{ t('requests.detail.back') }}
      </RouterLink>
      <div class="request-detail__heading">
        <div class="request-detail__identity">
          <h1 class="request-detail__title">
            <bdi v-if="detail" dir="ltr">{{ detail.number }}</bdi>
            <template v-else>{{ t('requests.detail.title') }}</template>
          </h1>
          <p v-if="detail" class="request-detail__number">
            <PathBadge v-if="detail.path" :path="detail.path" :label="detail.pathLabel" />
            <StatusBadge :status="detail.status" />
          </p>
        </div>
        <RequestHeaderActions
          v-if="status === 'ready' && detail"
          :detail="detail"
          :assignment-mode="assignmentMode"
          :bundle-requesting="bundles.requesting.value"
          :can-return="canReturn"
          :can-withdraw="canWithdraw"
          @assign="openDialog('assign', $event)"
          @bundle="bundles.requestBundle"
          @return="openDialog('return')"
          @withdraw="openDialog('withdraw')"
          @reopen="openDialog('reopen')"
          @force="openDialog('force')"
        />
      </div>
    </header>
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
      <Card class="request-detail__facts">
        <template #header>
          <h2 class="card__title-text">{{ t('requests.detail.requestInfo') }}</h2>
        </template>
        <RequestFacts :detail="detail" />
      </Card>
      <Tabs v-model="tab" :tabs="tabs" class="request-detail__tabs">
        <template #default="{ active }">
          <RequestOverviewTab
            v-if="active === TABS.OVERVIEW"
            :detail="detail"
            :receipt-downloading="receipts.busy.receipt === true"
            @download-receipt="downloadReceipt"
          />
          <div v-else-if="active === TABS.COMPLETION" class="request-detail__completions">
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
