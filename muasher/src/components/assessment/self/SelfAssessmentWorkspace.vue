<script setup>
import { computed, nextTick, onMounted, ref, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import SelfAssessmentSummary from './SelfAssessmentSummary.vue'
import SelfAssessmentNotices from './SelfAssessmentNotices.vue'
import SelfMainScoring from './SelfMainScoring.vue'
import SelfAssessmentActions from './SelfAssessmentActions.vue'
import SelfSubmitDialog from './SelfSubmitDialog.vue'
import SessionExtendDialog from '@/components/session/SessionExtendDialog.vue'
import { useSelfAssessmentForm } from '@/composables/useSelfAssessmentForm'
import { useSelfScoreSheet } from '@/composables/useSelfScoreSheet'
import { useSelfAssessmentActions } from '@/composables/useSelfAssessmentActions'
import { useCriterionSteps } from '@/composables/useCriterionSteps'
import { useSessionExtensionPrompt } from '@/composables/useSessionExtensionPrompt'

const props = defineProps({
  assessmentId: { type: Number, required: true },
})

const emit = defineEmits(['submitted'])
const { t } = useI18n()

const formState = useSelfAssessmentForm(toRef(props, 'assessmentId'))
const { status, errorMessage, form, assessment, units, isReadOnly, load } = formState
const scoreSheet = useSelfScoreSheet({ form, units })
const actions = useSelfAssessmentActions({
  formState,
  scoreSheet,
  onMissing: (missing) => revealMain(missing[0].mainId),
  onSubmitted: (result) => emit('submitted', result),
})
const { saving, submitting, confirmOpen, conflictCode } = actions
const refreshing = ref(false)
const scoring = ref(null)

const mains = computed(() => form.value?.criteria?.mains ?? [])
const steps = useCriterionSteps(mains)
const session = useSessionExtensionPrompt({ saveBeforeSignOut: saveIfEditable })
const activeMain = steps.current
const fieldsDisabled = computed(() => isReadOnly.value || saving.value || submitting.value)

watch(form, (current) => {
  if (!current) return
  scoreSheet.resetFrom(current.scores)
})

function saveIfEditable() {
  return isReadOnly.value ? Promise.resolve() : actions.save()
}

async function showStep(move) {
  move()
  await nextTick()
  scoring.value?.focusTitle()
}

function revealMain(mainId) {
  return showStep(() => steps.goToMain(mainId))
}

async function refresh() {
  refreshing.value = true
  actions.clearConflict()
  await load()
  refreshing.value = false
}

onMounted(load)
</script>

<template>
  <div class="self-workspace">
    <div v-if="status === 'loading' && !form" class="self-workspace__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="6" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
    <EmptyState
      v-else-if="status === 'missing'"
      icon="doc"
      :title="t('assessment.self.missing.title')"
      :message="t('assessment.self.missing.message')"
    />
    <template v-else-if="form">
      <SelfAssessmentSummary
        :assessment="assessment"
        :criteria="form.criteria"
        :scale="form.scale"
        :progress="form.progress"
      />
      <SelfAssessmentNotices
        :assessment="assessment"
        :conflict-code="conflictCode"
        :criteria-update="form.criteriaUpdate"
        :refreshing="refreshing"
        @refresh="refresh"
      />
      <SelfMainScoring
        v-if="activeMain"
        :key="activeMain.id"
        ref="scoring"
        :main="activeMain"
        :step="steps.position.value + 1"
        :total="steps.total.value"
        :scores="form.scores"
        :maximum="scoreSheet.maximum.value"
        :disabled="fieldsDisabled"
      />
      <SelfAssessmentActions
        :saving="saving"
        :submitting="submitting"
        :read-only="isReadOnly"
        :submit-blocked="assessment.criteriaUpdateInProgress"
        :dirty="scoreSheet.dirty.value"
        :is-first="steps.isFirst.value"
        :is-last="steps.isLast.value"
        @previous="showStep(steps.previous)"
        @next="showStep(steps.next)"
        @save="actions.save"
        @submit="actions.requestSubmit"
      />
      <SessionExtendDialog
        :open="session.open.value"
        :countdown="session.countdown.value"
        :busy="session.busy.value"
        @extend="session.extend"
        @sign-out="session.signOut"
      />
      <SelfSubmitDialog
        v-model="confirmOpen"
        :number="assessment.number"
        :submitting="submitting"
        @confirm="actions.confirmSubmit"
      />
    </template>
  </div>
</template>
