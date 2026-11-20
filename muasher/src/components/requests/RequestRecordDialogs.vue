<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import AssignAssessorDialog from './AssignAssessorDialog.vue'
import ForceTransitionDialog from './ForceTransitionDialog.vue'
import RequestCompletionDialog from '@/components/review/RequestCompletionDialog.vue'
import { useReturnForCompletion } from '@/composables/useReturnForCompletion'
import { useSuperAdminReason } from '@/composables/useSuperAdminReason'
import { requestsService } from '@/services/requestsService'
import { assessmentOverridesService } from '@/services/assessmentOverridesService'
import { AWARD_LIMITS } from '@/constants/advanced'
import { ACCREDITED_LIMITS, ASSIGNMENT_MODES } from '@/constants/accredited'
import { localizedText } from '@/utils/localizedText'

// Every dialog the request page's header actions open. `applied` carries a fresh request; `changed` asks for a reload.
const props = defineProps({
  detail: { type: Object, required: true },
})

const emit = defineEmits(['applied', 'changed'])
const { t, locale } = useI18n()
const returning = useReturnForCompletion()
const { prompt: reasonPrompt, reasonExtra } = useSuperAdminReason()
const assignMode = ref(ASSIGNMENT_MODES.ASSIGN)
const opened = ref('')

async function open(kind, mode = ASSIGNMENT_MODES.ASSIGN) {
  if (kind === 'return' && !(await returning.prepare(props.detail))) return
  assignMode.value = mode
  opened.value = kind
}

function model(kind) {
  return {
    modelValue: opened.value === kind,
    'onUpdate:modelValue': (value) => (opened.value = value ? kind : ''),
  }
}

async function askReason() {
  const extra = await reasonExtra()
  return extra?.reason ?? null
}

async function withdraw(reason) {
  const { data } = await requestsService.withdraw(props.detail.id, { reason })
  return data
}

function reopen(reason) {
  return assessmentOverridesService.reopen(props.detail.id, { reason })
}

defineExpose({ open })
</script>

<template>
  <AssignAssessorDialog
    v-bind="model('assign')"
    :request-id="detail.id"
    :mode="assignMode"
    :entity-name="localizedText(detail.entity?.name, locale)"
    :path="detail.path ?? ''"
    :path-label="detail.pathLabel"
    @done="emit('applied', $event)"
  />
  <ReasonDialog
    v-bind="model('reopen')"
    :title="t('requests.overrides.reopenTitle')"
    :subtitle="t('requests.overrides.reopenMessage')"
    :submit-label="t('requests.overrides.reopen')"
    :min-length="ACCREDITED_LIMITS.OVERRIDE_REASON_MIN"
    :action="reopen"
    action-name="overrides.reopen"
    @done="emit('changed')"
  />
  <RequestCompletionDialog
    v-if="returning.criteria.value"
    v-bind="model('return')"
    variant="follower"
    :assessment-id="detail.id"
    :criteria="returning.criteria.value"
    :bounds="returning.bounds.value"
    :send="requestsService.returnForCompletion"
    :ask-reason="askReason"
    @done="emit('applied', $event)"
  />
  <ReasonDialog
    v-bind="model('withdraw')"
    :title="t('requests.withdraw.title')"
    :subtitle="detail.number"
    destructive
    :submit-label="t('requests.withdraw.confirm')"
    :reason-label="t('requests.withdraw.reason')"
    :min-length="AWARD_LIMITS.REASON_MIN"
    :action="withdraw"
    action-name="requests.withdraw"
    @done="emit('applied', $event)"
  />
  <ReasonDialog
    v-model="reasonPrompt.open.value"
    :title="t('review.reasonTitle')"
    :submit-label="t('actions.confirm')"
    :action="reasonPrompt.provide"
    action-name="requests.superAdminReason"
  />
  <ForceTransitionDialog
    v-bind="model('force')"
    :assessment-id="detail.id"
    :current-status="detail.status"
    @done="emit('changed')"
  />
</template>
