<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, RouterLink } from 'vue-router'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import AssessmentTimeline from '@/components/assessment/AssessmentTimeline.vue'
import CompletionRequestCard from '@/components/accredited/CompletionRequestCard.vue'
import RequestFacts from '@/components/requests/RequestFacts.vue'
import RequestActionsCard from '@/components/requests/RequestActionsCard.vue'
import AssignmentHistoryList from '@/components/requests/AssignmentHistoryList.vue'
import AssignAssessorDialog from '@/components/requests/AssignAssessorDialog.vue'
import ForceTransitionDialog from '@/components/requests/ForceTransitionDialog.vue'
import RequestCompletionDialog from '@/components/review/RequestCompletionDialog.vue'
import { useReturnForCompletion } from '@/composables/useReturnForCompletion'
import { useSuperAdminReason } from '@/composables/useSuperAdminReason'
import { requestsService } from '@/services/requestsService'
import { AWARD_LIMITS } from '@/constants/advanced'
import { useRequestDetail } from '@/composables/useRequestDetail'
import { useEvidenceBundleRequest } from '@/composables/useEvidenceBundle'
import { usePermissions } from '@/composables/usePermissions'
import { assessmentOverridesService } from '@/services/assessmentOverridesService'
import { ACCREDITED_LIMITS } from '@/constants/accredited'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'

const { t, locale } = useI18n()
const route = useRoute()
const { can } = usePermissions()
const requestId = computed(() => Number(route.params.id))
const { status, errorMessage, detail, canAssign, canReassign, canReturn, canWithdraw, load, apply } = useRequestDetail(requestId)
const returning = useReturnForCompletion()
const { prompt: reasonPrompt, reasonExtra } = useSuperAdminReason()
const returnOpen = ref(false)
const withdrawOpen = ref(false)
const bundles = useEvidenceBundleRequest(requestId)
const assignOpen = ref(false)
const assignMode = ref('assign')
const reopenOpen = ref(false)
const forceOpen = ref(false)

useHead({ title: () => t('requests.detail.title') })

const backRoute = computed(() => (can(PERMISSIONS.REQUESTS_QUEUE_VIEW) ? ROUTE_NAMES.REQUESTS_INDEX : ROUTE_NAMES.REQUESTS_ASSIGNED))

function openAssign(mode) {
  assignMode.value = mode
  assignOpen.value = true
}

async function askReason() {
  const extra = await reasonExtra()
  return extra?.reason ?? null
}

async function openReturn() {
  if (await returning.prepare(detail.value)) returnOpen.value = true
}

async function withdraw(reason) {
  const { data } = await requestsService.withdraw(requestId.value, { reason })
  return data
}

function reopen(reason) {
  return assessmentOverridesService.reopen(requestId.value, { reason })
}

onMounted(load)
</script>

<template>
  <section class="request-detail">
    <header class="request-detail__header">
      <RouterLink class="request-detail__back" :to="{ name: backRoute }">
        <i class="fa-solid fa-arrow-left-long u-flip-in-rtl" aria-hidden="true"></i>
        {{ t('requests.detail.back') }}
      </RouterLink>
      <h1 class="request-detail__title">{{ t('requests.detail.title') }}</h1>
      <p v-if="detail" class="request-detail__number"><bdi dir="ltr">{{ detail.number }}</bdi></p>
    </header>
    <div v-if="status === 'loading'" class="request-detail__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="5" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
    <EmptyState v-else-if="status === 'missing'" icon="fa-regular fa-folder-open" :title="t('requests.detail.missingTitle')" :message="t('requests.detail.missingMessage')" />
    <div v-else-if="detail" class="request-detail__layout">
      <div class="request-detail__main">
        <Card>
          <template #header><h2 class="card__title-text">{{ t('requests.detail.requestInfo') }}</h2></template>
          <RequestFacts :detail="detail" />
        </Card>
        <Card v-for="completion in detail.completionRequests" :key="completion.id">
          <CompletionRequestCard :request="completion" show-status />
        </Card>
        <Card>
          <template #header><h2 class="card__title-text">{{ t('requests.detail.historyTitle') }}</h2></template>
          <AssignmentHistoryList :history="detail.assignmentHistory" />
        </Card>
        <Card>
          <template #header><h2 class="card__title-text">{{ t('requests.detail.timelineTitle') }}</h2></template>
          <AssessmentTimeline :history="detail.record.statusHistory" />
        </Card>
      </div>
      <aside class="request-detail__aside">
        <RequestActionsCard
          :detail="detail"
          :can-assign="canAssign"
          :can-reassign="canReassign"
          :bundle-requesting="bundles.requesting.value"
          @assign="openAssign('assign')"
          @reassign="openAssign('reassign')"
          @bundle="bundles.requestBundle"
          :can-return="canReturn"
          :can-withdraw="canWithdraw"
          @reopen="reopenOpen = true"
          @force="forceOpen = true"
          @return="openReturn"
          @withdraw="withdrawOpen = true"
        />
      </aside>
      <AssignAssessorDialog v-model="assignOpen" :request-id="detail.id" :mode="assignMode" :entity-name="localizedText(detail.entity?.name, locale)" @done="apply" />
      <ReasonDialog
        v-model="reopenOpen"
        :title="t('requests.overrides.reopenTitle')"
        :message="t('requests.overrides.reopenMessage')"
        :submit-label="t('requests.overrides.reopen')"
        :min-length="ACCREDITED_LIMITS.OVERRIDE_REASON_MIN"
        :action="reopen"
        action-name="overrides.reopen"
        @done="load({ silent: true })"
      />
      <RequestCompletionDialog
        v-if="returning.criteria.value"
        v-model="returnOpen"
        variant="follower"
        :assessment-id="detail.id"
        :criteria="returning.criteria.value"
        :bounds="returning.bounds.value"
        :send="requestsService.returnForCompletion"
        :ask-reason="askReason"
        @done="apply"
      />
      <ReasonDialog
        v-model="withdrawOpen"
        :title="t('requests.withdraw.title')"
        :message="t('requests.withdraw.message', { number: detail.number })"
        :submit-label="t('requests.withdraw.confirm')"
        :reason-label="t('requests.withdraw.reason')"
        :min-length="AWARD_LIMITS.REASON_MIN"
        :action="withdraw"
        action-name="requests.withdraw"
        @done="apply"
      />
      <ReasonDialog v-model="reasonPrompt.open.value" :title="t('review.reasonTitle')" :message="t('review.reasonMessage')" :submit-label="t('actions.confirm')" :action="reasonPrompt.provide" action-name="requests.superAdminReason" />
      <ForceTransitionDialog v-model="forceOpen" :assessment-id="detail.id" :current-status="detail.status" @done="load({ silent: true })" />
    </div>
  </section>
</template>
