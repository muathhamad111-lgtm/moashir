<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, RouterLink } from 'vue-router'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import PageLoading from '@/components/feedback/PageLoading.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import AccreditedFeedbackList from '@/components/accredited/AccreditedFeedbackList.vue'
import ReviewEvidenceList from '@/components/review/ReviewEvidenceList.vue'
import FilteringFacts from '@/components/award/FilteringFacts.vue'
import FilteringDecisionList from '@/components/award/FilteringDecisionList.vue'
import FilteringDecisionDialog from '@/components/award/FilteringDecisionDialog.vue'
import { useFilteringDetail } from '@/composables/useFilteringDetail'
import { filteringWindowOf } from '@/utils/award/filteringWindow'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { useToast } from '@/composables/useToast'
import { evidenceService } from '@/services/evidenceService'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const cycleId = computed(() => Number(route.params.id))
const assessmentId = computed(() => Number(route.params.assessment))
const filtering = useFilteringDetail(cycleId, assessmentId)
const { status, errorMessage, detail, cycle, row, windowOpen, mayDecide } = filtering
const windowState = computed(() => filteringWindowOf(cycle.value?.status))
const downloads = useSignedDownload()
const dialogMode = ref('')

useHead({ title: () => t('filtering.detail.title') })

const dialogOpen = computed({
  get: () => Boolean(dialogMode.value),
  set: (open) => {
    if (!open) dialogMode.value = ''
  },
})

function download(file) {
  downloads.download(file.id, () => evidenceService.requestDownload(assessmentId.value, file.id))
}

function onDecided() {
  toast.success(t('filtering.decision.done'))
  filtering.load({ silent: true })
}

onMounted(filtering.load)
</script>

<template>
  <section class="request-detail">
    <header class="request-detail__header">
      <RouterLink
        class="request-detail__back"
        :to="{ name: ROUTE_NAMES.AWARD_FILTERING_QUEUE, params: { id: cycleId } }"
      >
        <AppIcon name="arrow-left" />
        {{ t('filtering.detail.back') }}
      </RouterLink>
      <h1 class="request-detail__title">{{ t('filtering.detail.title') }}</h1>
      <p v-if="row" class="request-detail__number">
        <bdi dir="ltr">{{ row.number }}</bdi> <StatusBadge :status="row.status" />
      </p>
    </header>
    <PageLoading v-if="status === 'loading'" />
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="filtering.load" />
    <EmptyState
      v-else-if="status === 'missing'"
      icon="folder"
      :title="t('filtering.detail.missingTitle')"
      :message="t('filtering.detail.missingMessage')"
    />
    <EmptyState
      v-else-if="status === 'blocked'"
      icon="lock"
      :title="t('filtering.detail.blockedTitle')"
      :message="errorMessage || t('filtering.detail.blockedMessage')"
    />
    <div v-else-if="detail" class="award-detail__body">
      <AssessmentNotice
        v-if="row.isFrozen"
        variant="frozen"
        :title="t('accredited.notices.frozen.title')"
        :message="t('accredited.notices.frozen.message')"
      />
      <AssessmentNotice
        v-if="!windowOpen && mayDecide"
        variant="info"
        :title="t(`filtering.window.${windowState}.title`)"
        :message="t(`filtering.window.${windowState}.message`)"
      />
      <Card :title="t('filtering.detail.facts')"><FilteringFacts :detail="detail" /></Card>
      <Card v-if="detail.result.available" :title="t('filtering.detail.result')">
        <p class="filtering-detail__read-only" role="note">
          {{ t('filtering.detail.readOnly') }}
        </p>
        <AccreditedFeedbackList :feedback="detail.result.feedback" />
      </Card>
      <Card :title="t('filtering.detail.evidence')">
        <ReviewEvidenceList
          :files="detail.review.evidence"
          :downloading="downloads.busy"
          @download="download"
        />
      </Card>
      <details v-if="detail.decisions.length" class="award-detail__history card">
        <summary>{{ t('filtering.detail.decisions') }} ({{ detail.decisions.length }})</summary>
        <FilteringDecisionList :decisions="detail.decisions" />
      </details>
      <div
        v-if="mayDecide"
        class="filtering-actions award-action-bar review-actions--sticky"
        role="group"
        :aria-label="t('filtering.detail.decide')"
      >
        <div class="filtering-actions__buttons">
          <BaseButton
            variant="primary"
            icon="check-circle"
            :disabled="!windowOpen"
            @click="dialogMode = 'qualify'"
            >{{ t('filtering.decision.qualify.label') }}</BaseButton
          >
          <BaseButton
            variant="danger"
            icon="x"
            :disabled="!windowOpen"
            @click="dialogMode = 'disqualify'"
            >{{ t('filtering.decision.disqualify.label') }}</BaseButton
          >
        </div>
      </div>
    </div>
    <FilteringDecisionDialog
      v-if="detail && dialogMode"
      v-model="dialogOpen"
      :mode="dialogMode"
      :cycle-id="cycleId"
      :row="row"
      :threshold="detail.disqualificationScore"
      @done="onDecided"
    />
  </section>
</template>
