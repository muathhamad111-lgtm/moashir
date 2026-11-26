<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import PageLoading from '@/components/feedback/PageLoading.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import SecretaryFacts from '@/components/committee/SecretaryFacts.vue'
import SecretaryEvaluationTable from '@/components/committee/SecretaryEvaluationTable.vue'
import SecretaryEvaluationDialog from '@/components/committee/SecretaryEvaluationDialog.vue'
import SecretaryDecisionList from '@/components/committee/SecretaryDecisionList.vue'
import SecretaryDiscussionPanel from '@/components/committee/SecretaryDiscussionPanel.vue'
import SecretaryActionBar from '@/components/committee/SecretaryActionBar.vue'
import SecretaryDecisionDialog from '@/components/committee/SecretaryDecisionDialog.vue'
import { useSecretaryRequest } from '@/composables/useSecretaryRequest'
import { useSecretaryActions } from '@/composables/useSecretaryActions'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t, locale } = useI18n()
const route = useRoute()
const cycleId = computed(() => Number(route.params.id))
const assessmentId = computed(() => Number(route.params.assessment))
const request = useSecretaryRequest(cycleId, assessmentId)
const { status, errorMessage, detail, decides, flags, approvesSubstitution } = request
const actions = useSecretaryActions({ cycleId, assessmentId, request })
const viewing = ref(null)

useHead({ title: () => t('award.secretary.detailTitle') })

const viewingOpen = computed({
  get: () => viewing.value !== null,
  set: (open) => {
    if (!open) viewing.value = null
  },
})
const subject = computed(() =>
  detail.value
    ? `${detail.value.number} — ${localizedText(detail.value.entity?.name, locale.value)}`
    : '',
)

onMounted(request.load)
</script>

<template>
  <section class="request-detail secretary-request">
    <header class="request-detail__header">
      <RouterLink
        class="request-detail__back"
        :to="{ name: ROUTE_NAMES.AWARD_SECRETARY_DESK, params: { id: cycleId } }"
      >
        <AppIcon name="arrow-left" />
        {{ t('award.secretary.back') }}
      </RouterLink>
      <h1 class="request-detail__title">{{ t('award.secretary.detailTitle') }}</h1>
      <p v-if="detail" class="request-detail__number">
        <bdi dir="ltr">{{ detail.number }}</bdi>
      </p>
    </header>
    <PageLoading v-if="status === 'loading'" />
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="request.load" />
    <EmptyState
      v-else-if="status === 'missing' || status === 'blocked'"
      icon="lock"
      :title="t('award.secretary.unavailableTitle')"
      :message="errorMessage || t('award.secretary.unavailableMessage')"
    />
    <div v-else-if="detail" class="award-detail__body">
      <p v-if="!decides" class="secretary-request__read-only" role="note">
        {{ t('award.secretary.readOnly') }}
      </p>
      <Card :title="t('award.secretary.factsTitle')"><SecretaryFacts :detail="detail" /></Card>
      <Card :title="t('award.secretary.evaluationsTitle')">
        <SecretaryEvaluationTable
          :evaluations="detail.evaluations"
          :approves-substitution="approvesSubstitution"
          @view="viewing = $event"
          @approve-substitution="actions.open('approveSubstitution', $event)"
        />
      </Card>
      <Card v-if="detail.discussions.length" :title="t('award.secretary.discussionsTitle')">
        <SecretaryDiscussionPanel
          :discussions="detail.discussions"
          :can-close="decides"
          @close="actions.open('closeDiscussion', $event)"
        />
      </Card>
      <details v-if="detail.decisions.length" class="award-detail__history card">
        <summary>{{ t('award.secretary.decisionsTitle') }} ({{ detail.decisions.length }})</summary>
        <SecretaryDecisionList :decisions="detail.decisions" />
      </details>
      <SecretaryActionBar
        v-if="decides"
        :flags="flags"
        :recomputing="actions.recomputing.value"
        :pending-substitutions="detail.pendingSubstitutionIds.length"
        @recompute="actions.recompute"
        @open="actions.open"
      />
    </div>
    <SecretaryEvaluationDialog
      v-if="viewing"
      v-model="viewingOpen"
      :cycle-id="cycleId"
      :assessment-id="assessmentId"
      :assignment="viewing"
    />
    <SecretaryDecisionDialog
      v-if="actions.dialog.value"
      :key="actions.dialog.value.kind"
      v-model="actions.dialogOpen.value"
      :kind="actions.dialog.value.kind"
      :subject="subject"
      :send="actions.send"
    />
    <ReasonDialog
      v-model="actions.reasons.prompt.open.value"
      :title="t('review.reasonTitle')"
      :submit-label="t('actions.confirm')"
      :action="actions.reasons.prompt.provide"
      action-name="secretary.superAdminReason"
    />
  </section>
</template>
