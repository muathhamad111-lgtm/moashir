<script setup>
import { computed, onMounted, ref, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import CriteriaTree from '@/components/criteria/CriteriaTree.vue'
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
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  assignmentId: { type: Number, required: true },
})

const { t, locale } = useI18n()
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
const activeMainId = ref(null)

const mains = computed(() => evaluation.value?.criteria?.mains ?? [])
const activeMain = computed(
  () => mains.value.find((main) => main.id === activeMainId.value) ?? mains.value[0] ?? null,
)
const dirty = computed(() => scoreSheet.dirty.value || notes.dirty.value)
const fullyScored = computed(
  () => evaluation.value && evaluation.value.progress.scored === evaluation.value.progress.required,
)
const treeNodes = computed(() =>
  mains.value.map((main) => ({
    id: main.id,
    label: `${main.code} ${localizedText(main.name, locale.value)}`,
    children: main.subs.map((sub) => ({
      id: sub.id,
      label: `${sub.code} ${localizedText(sub.name, locale.value)}`,
    })),
  })),
)

watch(evaluation, (current, previous) => {
  if (!current || current === previous) return
  if (!scoreSheet.dirty.value) scoreSheet.resetFrom(current.scores)
  if (!notes.dirty.value) notes.resetFrom(current.notes)
})

function selectNode({ node, parent }) {
  activeMainId.value = parent ? parent.id : node.id
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
      <CommitteeEntityCard :entity="evaluation.assignment.entity" />
      <div class="review-workspace__layout">
        <aside class="review-workspace__nav" :aria-label="t('review.navLabel')">
          <CriteriaTree
            :nodes="treeNodes"
            :active-id="activeMain?.id ?? null"
            @select="selectNode"
          />
        </aside>
        <CommitteeMainPanel
          v-if="activeMain"
          :key="activeMain.id"
          :main="activeMain"
          :workspace="workspace"
          @download="download"
        />
      </div>
      <CommitteeEvaluationActions
        :busy="actions.busy.value"
        :dirty="dirty"
        :can-score="canScore"
        :complete="fullyScored"
        :general-note="notes.draft.value.general"
        @update:general-note="notes.setGeneral"
        @save="actions.save"
        @submit="onSubmit"
      />
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
