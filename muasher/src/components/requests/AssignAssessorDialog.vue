<script setup>
import { computed, ref, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import { useAssignAssessor } from '@/composables/useAssignAssessor'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { ACCREDITED_LIMITS } from '@/constants/accredited'
import { logger } from '@/utils/logger'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  requestId: { type: Number, required: true },
  mode: { type: String, default: 'assign' },
  entityName: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'done'])
const { t } = useI18n()
const toast = useToast()
const auth = useAuthStore()
const { assessors, status, search, loadAssessors, submit } = useAssignAssessor({ requestId: toRef(props, 'requestId') })
const chosen = ref(null)
const reason = ref('')
const errors = ref({})
const busy = ref(false)

const reasonRequired = computed(() => props.mode === 'reassign' || auth.isSuperAdmin)
const title = computed(() => (props.mode === 'reassign' ? t('requests.assign.reassignTitle') : t('requests.assign.title')))

function validate() {
  const next = {}
  const text = reason.value.trim()
  if (!chosen.value) next.assessorId = t('requests.assign.chooseError')
  if (reasonRequired.value && !text) next.reason = t('validation.required')
  else if (text && text.length < ACCREDITED_LIMITS.REASSIGN_REASON_MIN) next.reason = t('validation.minChars', { min: ACCREDITED_LIMITS.REASSIGN_REASON_MIN })
  else if (text.length > ACCREDITED_LIMITS.REASON_MAX) next.reason = t('entities.validation.maxChars', { max: ACCREDITED_LIMITS.REASON_MAX })
  errors.value = next
  return !Object.keys(next).length
}

async function onSubmit() {
  if (busy.value || !validate()) return
  busy.value = true
  const payload = { assessorId: chosen.value, ...(reason.value.trim() ? { reason: reason.value.trim() } : {}) }
  logger.breadcrumb('form', `requests.${props.mode}.submit`, { fields: Object.keys(payload) })
  try {
    const { data } = await submit(props.mode, payload)
    toast.success(t(props.mode === 'reassign' ? 'requests.toasts.reassigned' : 'requests.toasts.assigned'))
    emit('update:modelValue', false)
    emit('done', data)
  } catch (error) {
    const fields = error?.normalized?.fieldErrors ?? {}
    errors.value = { assessorId: fields.assessorId?.[0] ?? '', reason: fields.reason?.[0] ?? '' }
    toast.error(error?.normalized?.message || t('errors.unexpected'))
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
  <FormModal :model-value="modelValue" :title="title" :busy="busy" :submit-label="mode === 'reassign' ? t('requests.assign.reassignAction') : t('requests.assign.action')" @update:model-value="emit('update:modelValue', $event)" @submit="onSubmit">
    <div class="assign-dialog">
      <p class="assign-dialog__lead">{{ t('requests.assign.lead', { entity: entityName }) }}</p>
      <BaseInput v-model="search" name="assessor-search" :label="t('requests.assign.searchLabel')" autocomplete="off" />
      <Skeleton v-if="status === 'loading'" variant="text" :count="3" />
      <p v-else-if="status === 'empty'" class="assign-dialog__empty">{{ t('requests.assign.empty') }}</p>
      <p v-else-if="status === 'error'" class="assign-dialog__empty" role="alert">{{ t('requests.assign.loadFailed') }}</p>
      <fieldset v-else class="assign-dialog__options" :aria-describedby="errors.assessorId ? 'assign-assessor-error' : undefined">
        <legend class="assign-dialog__legend">{{ t('requests.assign.assessorLabel') }}</legend>
        <label v-for="assessor in assessors" :key="assessor.id" class="assign-dialog__option">
          <input v-model="chosen" type="radio" name="assessorId" :value="assessor.id" class="assign-dialog__radio" />
          <span class="assign-dialog__name">{{ assessor.name }}</span>
          <span class="assign-dialog__load">{{ t('requests.assign.openRequests', { count: assessor.openRequests }, assessor.openRequests) }}</span>
        </label>
      </fieldset>
      <p v-if="errors.assessorId" id="assign-assessor-error" class="field__error" role="alert">{{ errors.assessorId }}</p>
      <BaseTextarea
        v-model="reason"
        name="reason"
        :label="t('requests.assign.reasonLabel')"
        :hint="reasonRequired ? t('requests.assign.reasonRequiredHint', { min: ACCREDITED_LIMITS.REASSIGN_REASON_MIN }) : t('requests.assign.reasonOptionalHint')"
        :error="errors.reason"
        :required="reasonRequired"
      />
    </div>
  </FormModal>
</template>
