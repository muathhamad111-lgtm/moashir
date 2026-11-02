<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, RouterLink } from 'vue-router'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import AccreditedFeedbackList from '@/components/accredited/AccreditedFeedbackList.vue'
import ReviewEvidenceList from '@/components/review/ReviewEvidenceList.vue'
import FilteringFacts from '@/components/award/FilteringFacts.vue'
import FilteringDecisionList from '@/components/award/FilteringDecisionList.vue'
import FilteringDecisionDialog from '@/components/award/FilteringDecisionDialog.vue'
import { useFilteringDetail } from '@/composables/useFilteringDetail'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { useToast } from '@/composables/useToast'
import { evidenceService } from '@/services/evidenceService'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const cycleId = computed(() => Number(route.params.id))
const assessmentId = computed(() => Number(route.params.assessment))
const filtering = useFilteringDetail(cycleId, assessmentId)
const { status, errorMessage, detail, row, windowOpen, mayDecide } = filtering
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
      <RouterLink class="request-detail__back" :to="{ name: ROUTE_NAMES.AWARD_FILTERING_QUEUE, params: { id: cycleId } }">
        <i class="fa-solid fa-arrow-left-long u-flip-in-rtl" aria-hidden="true"></i>
        {{ t('filtering.detail.back') }}
      </RouterLink>
      <h1 class="request-detail__title">{{ t('filtering.detail.title') }}</h1>
      <p v-if="row" class="request-detail__number"><bdi dir="ltr">{{ row.number }}</bdi> <StatusBadge :status="row.status" /></p>
    </header>
    <div v-if="status === 'loading'" class="request-detail__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="6" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="filtering.load" />
    <EmptyState v-else-if="status === 'missing'" icon="fa-regular fa-folder-open" :title="t('filtering.detail.missingTitle')" :message="t('filtering.detail.missingMessage')" />
    <EmptyState v-else-if="status === 'blocked'" icon="fa-solid fa-lock" :title="t('filtering.detail.blockedTitle')" :message="errorMessage || t('filtering.detail.blockedMessage')" />
    <div v-else-if="detail" class="request-detail__layout">
      <div class="request-detail__main">
        <AssessmentNotice v-if="row.isFrozen" variant="frozen" :title="t('accredited.notices.frozen.title')" :message="t('accredited.notices.frozen.message')" />
        <AssessmentNotice v-if="!windowOpen && mayDecide" variant="info" :title="t('filtering.window.title')" :message="t('filtering.window.message')" />
        <Card :title="t('filtering.detail.facts')"><FilteringFacts :detail="detail" /></Card>
        <Card v-if="detail.result.available" :title="t('filtering.detail.result')">
          <p class="filtering-detail__read-only" role="note">{{ t('filtering.detail.readOnly') }}</p>
          <AccreditedFeedbackList :feedback="detail.result.feedback" />
        </Card>
        <Card :title="t('filtering.detail.evidence')">
          <ReviewEvidenceList :files="detail.review.evidence" :downloading="downloads.busy" @download="download" />
        </Card>
      </div>
      <aside class="request-detail__aside">
        <Card v-if="mayDecide" class="filtering-actions" :title="t('filtering.detail.decide')">
          <div class="filtering-actions__buttons">
            <BaseButton variant="primary" icon="fa-solid fa-circle-check" :disabled="!windowOpen" @click="dialogMode = 'qualify'">{{ t('filtering.decision.qualify.label') }}</BaseButton>
            <BaseButton variant="danger" icon="fa-solid fa-circle-xmark" :disabled="!windowOpen" @click="dialogMode = 'disqualify'">{{ t('filtering.decision.disqualify.label') }}</BaseButton>
          </div>
        </Card>
        <Card :title="t('filtering.detail.decisions')"><FilteringDecisionList :decisions="detail.decisions" /></Card>
      </aside>
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
