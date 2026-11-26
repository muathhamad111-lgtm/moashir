<script setup>
import { computed, ref, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import AppCombobox from '@/components/form/AppCombobox.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import PathBadge from '@/components/ui/PathBadge.vue'
import { assignmentFailureOf, useAssignAssessor } from '@/composables/useAssignAssessor'
import { useToast } from '@/composables/useToast'
import { success } from '@/composables/useSuccess'
import { useAuthStore } from '@/stores/auth'
import { ACCREDITED_LIMITS } from '@/constants/accredited'
import { logger } from '@/utils/logger'
import { ROUTE_NAMES } from '@/constants/routeNames'

const AWARD_PATH = 'advanced'

const LIST_MESSAGES = {
  empty: 'requests.assign.empty',
  noMatch: 'requests.assign.noMatch',
  cycleUnlinked: 'requests.assign.cycleUnlinked',
  outOfScope: 'requests.assign.outOfScope',
  error: 'requests.assign.loadFailed',
}

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  requestId: { type: Number, required: true },
  mode: { type: String, default: 'assign' },
  entityName: { type: String, default: '' },
  path: { type: String, default: '' },
  pathLabel: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'done'])
const { t } = useI18n()
const toast = useToast()
const auth = useAuthStore()
const { assessors, status, search, reasonRules, awardCycle, loadAssessors, submit } =
  useAssignAssessor({
    requestId: toRef(props, 'requestId'),
  })
const chosen = ref(null)
const reason = ref('')
const errors = ref({})
const busy = ref(false)

// The general settings decide per mode; the Super Admin always gives a reason (override rule).
const reasonRequired = computed(
  () =>
    auth.isSuperAdmin ||
    (props.mode === 'reassign'
      ? reasonRules.value.reassignRequired
      : reasonRules.value.assignRequired),
)
const options = computed(() =>
  assessors.value.map((assessor) => ({
    value: assessor.id,
    label: assessor.name,
    description: t(
      'requests.assign.openRequests',
      { count: assessor.openRequests },
      assessor.openRequests,
    ),
  })),
)
const listMessage = computed(() => LIST_MESSAGES[status.value] ?? '')
const pathHint = computed(() =>
  props.path === AWARD_PATH
    ? t('requests.assign.qualifiedOnlyAward')
    : t('requests.assign.qualifiedOnly'),
)
const cycleLink = computed(() =>
  status.value === 'cycleUnlinked' && awardCycle.value?.cycleId
    ? { name: ROUTE_NAMES.AWARD_CYCLE, params: { id: awardCycle.value.cycleId } }
    : null,
)
const title = computed(() =>
  props.mode === 'reassign' ? t('requests.assign.reassignTitle') : t('requests.assign.title'),
)

function assessorName() {
  return assessors.value.find((assessor) => assessor.id === chosen.value)?.name ?? ''
}

function validate() {
  const next = {}
  const text = reason.value.trim()
  if (!chosen.value) next.assessorId = t('requests.assign.chooseError')
  if (reasonRequired.value && !text) next.reason = t('validation.required')
  else if (text && text.length < ACCREDITED_LIMITS.REASSIGN_REASON_MIN)
    next.reason = t('validation.minChars', { min: ACCREDITED_LIMITS.REASSIGN_REASON_MIN })
  else if (text.length > ACCREDITED_LIMITS.REASON_MAX)
    next.reason = t('entities.validation.maxChars', { max: ACCREDITED_LIMITS.REASON_MAX })
  errors.value = next
  return !Object.keys(next).length
}

function handleFailure(failure) {
  if (failure.outOfScope) {
    toast.error(t('requests.assign.outOfScope'))
    emit('update:modelValue', false)
    return
  }
  const assessorError = failure.notQualified
    ? t('requests.assign.notQualified')
    : (failure.fields.assessorId?.[0] ?? '')
  errors.value = { assessorId: assessorError, reason: failure.fields.reason?.[0] ?? '' }
  toast.error(failure.message || t('errors.unexpected'))
  if (failure.notQualified) {
    chosen.value = null
    loadAssessors()
  }
}

async function onSubmit() {
  if (busy.value || !validate()) return
  busy.value = true
  const payload = {
    assessorId: chosen.value,
    ...(reason.value.trim() ? { reason: reason.value.trim() } : {}),
  }
  logger.breadcrumb('form', `requests.${props.mode}.submit`, { fields: Object.keys(payload) })
  try {
    const { data } = await submit(props.mode, payload)
    emit('update:modelValue', false)
    success.show({
      title: t(
        props.mode === 'reassign' ? 'requests.toasts.reassigned' : 'requests.toasts.assigned',
      ),
      message: assessorName(),
    })
    emit('done', data)
  } catch (error) {
    handleFailure(assignmentFailureOf(error))
  } finally {
    busy.value = false
  }
}

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    chosen.value = null
    reason.value = ''
    errors.value = {}
    search.value = ''
    loadAssessors()
  },
  { immediate: true },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="title"
    :subtitle="entityName"
    :busy="busy"
    :submit-label="
      mode === 'reassign' ? t('requests.assign.reassignAction') : t('requests.assign.action')
    "
    @update:model-value="emit('update:modelValue', $event)"
    @submit="onSubmit"
  >
    <div class="assign-dialog">
      <p v-if="path" class="assign-dialog__path">
        <PathBadge :path="path" :label="pathLabel" />
        <span>{{ pathHint }}</span>
      </p>
      <AppCombobox
        v-model="chosen"
        class="assign-dialog__assessor"
        name="assessorId"
        :options="options"
        :loading="status === 'loading'"
        remote
        :label="t('requests.assign.assessorLabel')"
        :placeholder="t('requests.assign.searchLabel')"
        :hint="listMessage ? t(listMessage) : ''"
        :error="errors.assessorId"
        required
        @search="search = $event"
      />
      <p v-if="cycleLink" class="assign-dialog__cycle-link">
        <RouterLink :to="cycleLink">{{ t('requests.assign.linkAssessors') }}</RouterLink>
      </p>
      <BaseTextarea
        v-model="reason"
        name="reason"
        :label="t('requests.assign.reasonLabel')"
        :hint="
          reasonRequired
            ? t('requests.assign.reasonRequiredHint', {
                min: ACCREDITED_LIMITS.REASSIGN_REASON_MIN,
              })
            : t('requests.assign.reasonOptionalHint')
        "
        :error="errors.reason"
        :required="reasonRequired"
      />
    </div>
  </FormModal>
</template>
