<script setup>
import { computed, nextTick, onMounted, ref, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ReviewSections from '@/components/review/ReviewSections.vue'
import AccreditedStations from '@/components/accredited/AccreditedStations.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import CommitteeEvaluationHeader from './CommitteeEvaluationHeader.vue'
import CommitteeEvaluationNotices from './CommitteeEvaluationNotices.vue'
import CommitteeEntityCard from './CommitteeEntityCard.vue'
import CommitteeMainPanel from './CommitteeMainPanel.vue'
import CommitteeEvaluationActions from './CommitteeEvaluationActions.vue'
import { useCommitteeEvaluation } from '@/composables/useCommitteeEvaluation'
import { useCommitteeNotes } from '@/composables/useCommitteeNotes'
import { useCommitteeEvaluationActions } from '@/composables/useCommitteeEvaluationActions'
import { useSelfScoreSheet } from '@/composables/useSelfScoreSheet'
import { useSuperAdminReason } from '@/composables/useSuperAdminReason'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { useConfirm } from '@/composables/useConfirm'
import { evidenceService } from '@/services/evidenceService'
import { useCriterionSteps } from '@/composables/useCriterionSteps'
import { scoredStationsOf } from '@/utils/assessments/scoredStations'

const props = defineProps({
  assignmentId: { type: Number, required: true },
})

const { t } = useI18n()
const { confirm } = useConfirm()
const state = useCommitteeEvaluation(toRef(props, 'assignmentId'))
const { status, errorMessage, evaluation, units, canScore } = state
const scoreSheet = useSelfScoreSheet({ form: evaluation, units })
const notes = useCommitteeNotes()
const reasons = useSuperAdminReason()
const actions = useCommitteeEvaluationActions({
  state,
  scoreSheet,
  notes,
  reasonExtra: reasons.reasonExtra,
})
const downloads = useSignedDownload()
const workspace = { state, scoreSheet, notes, actions, downloads }
const panelTop = ref(null)

const mains = computed(() => evaluation.value?.criteria?.mains ?? [])
const steps = useCriterionSteps(mains)
const activeMain = steps.current
const stations = computed(() => scoredStationsOf(mains.value, units.value, scoreSheet.sheet.values))
const dirty = computed(() => scoreSheet.dirty.value || notes.dirty.value)
const fullyScored = computed(
  () => evaluation.value && evaluation.value.progress.scored === evaluation.value.progress.required,
)
watch(evaluation, (current, previous) => {
  if (!current || current === previous) return
  if (!scoreSheet.dirty.value) scoreSheet.resetFrom(current.scores)
  if (!notes.dirty.value) notes.resetFrom(current.notes)
})

async function showStep(move) {
  move()
  await nextTick()
  panelTop.value?.scrollIntoView({ block: 'start' })
  panelTop.value?.focus()
}

function download(file) {
  return downloads.download(file.id, () =>
    evidenceService.requestDownload(state.assessmentId.value, file.id),
  )
}

async function onSubmit() {
  const message =
    fullyScored.value && !scoreSheet.dirty.value
      ? t('award.evaluation.confirmSubmit.message')
      : t('award.evaluation.confirmSubmit.pendingMessage')
  if (
    await confirm({
      title: t('award.evaluation.confirmSubmit.title'),
      message,
      confirmLabel: t('actions.send'),
      variant: 'confirm',
    })
  )
    actions.submit()
}

async function refresh() {
  actions.clearConflict()
  await state.load()
}

onMounted(state.load)
</script>

<template>
  <div class="review-workspace committee-workspace">
    <div v-if="status === 'loading'" class="review-workspace__loading" aria-busy="true">
      <Skeleton variant="block" /><Skeleton variant="text" :count="6" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="state.load" />
    <EmptyState
      v-else-if="status === 'missing' || status === 'blocked'"
      icon="lock"
      :title="t('award.evaluation.unavailableTitle')"
      :message="errorMessage || t('award.evaluation.unavailableMessage')"
    />
    <template v-else-if="evaluation">
      <CommitteeEvaluationHeader :evaluation="evaluation" />
      <CommitteeEvaluationNotices
        :evaluation="evaluation"
        :conflict-code="actions.conflictCode.value"
        @refresh="refresh"
      />
      <ReviewSections>
        <template #assessment>
          <AccreditedStations
            compact
            :stations="stations"
            :active-id="activeMain?.id ?? null"
            @select="showStep(() => steps.goToMain($event))"
          />
          <div ref="panelTop" class="review-workspace__main" tabindex="-1">
            <CommitteeMainPanel
              v-if="activeMain"
              :key="activeMain.id"
              :main="activeMain"
              :step="steps.position.value + 1"
              :total="steps.total.value"
              :workspace="workspace"
              @download="download"
            />
          </div>
          <CommitteeEvaluationActions
            :busy="actions.busy.value"
            :dirty="dirty"
            :can-score="canScore"
            :general-note="notes.draft.value.general"
            :is-first="steps.isFirst.value"
            :is-last="steps.isLast.value"
            @previous="showStep(steps.previous)"
            @next="showStep(steps.next)"
            @update:general-note="notes.setGeneral"
            @save="actions.save"
            @submit="onSubmit"
          />
        </template>
        <template #entity>
          <CommitteeEntityCard :entity="evaluation.assignment.entity" />
        </template>
      </ReviewSections>
      <ReasonDialog
        v-model="reasons.prompt.open.value"
        :title="t('review.reasonTitle')"
        :submit-label="t('actions.confirm')"
        :action="reasons.prompt.provide"
        action-name="committee.superAdminReason"
      />
    </template>
  </div>
</template>
