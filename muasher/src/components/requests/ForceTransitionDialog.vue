<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import { useToast } from '@/composables/useToast'
import { assessmentOverridesService } from '@/services/assessmentOverridesService'
import { ACCREDITED_LIMITS, FORCE_TARGETS } from '@/constants/accredited'
import { statusLabelKey } from '@/utils/canonicalStatus'
import { logger } from '@/utils/logger'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  assessmentId: { type: Number, required: true },
  currentStatus: { type: String, required: true },
})

const emit = defineEmits(['update:modelValue', 'done'])
const { t } = useI18n()
const toast = useToast()
const target = ref('')
const reason = ref('')
const errors = ref({})
const busy = ref(false)

const options = computed(() =>
  FORCE_TARGETS.filter((status) => status !== props.currentStatus).map((value) => ({
    value,
    label: t(statusLabelKey(value)),
  })),
)

function validate() {
  const next = {}
  const text = reason.value.trim()
  if (!target.value) next.toStatus = t('validation.required')
  if (text.length < ACCREDITED_LIMITS.OVERRIDE_REASON_MIN)
    next.reason = t('validation.minChars', { min: ACCREDITED_LIMITS.OVERRIDE_REASON_MIN })
  else if (text.length > ACCREDITED_LIMITS.REASON_MAX)
    next.reason = t('entities.validation.maxChars', { max: ACCREDITED_LIMITS.REASON_MAX })
  errors.value = next
  return !Object.keys(next).length
}

async function onSubmit() {
  if (busy.value || !validate()) return
  busy.value = true
  logger.breadcrumb('form', 'overrides.forceTransition.submit', { fields: ['toStatus', 'reason'] })
  try {
    const { data } = await assessmentOverridesService.forceTransition(props.assessmentId, {
      toStatus: target.value,
      reason: reason.value.trim(),
    })
    toast.success(t('requests.overrides.toasts.forced'))
    emit('update:modelValue', false)
    emit('done', data)
  } catch (error) {
    const fields = error?.normalized?.fieldErrors ?? {}
    errors.value = { toStatus: fields.toStatus?.[0] ?? '', reason: fields.reason?.[0] ?? '' }
    toast.error(error?.normalized?.message || t('errors.unexpected'))
  } finally {
    busy.value = false
  }
}

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    target.value = ''
    reason.value = ''
    errors.value = {}
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="t('requests.overrides.forceTitle')"
    :busy="busy"
    :submit-label="t('requests.overrides.forceAction')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="onSubmit"
  >
    <div class="override-dialog">
      <p class="override-dialog__warning" role="note">
        <AppIcon name="warning-triangle" />
        {{ t('requests.overrides.forceWarning') }}
      </p>
      <AppSelect
        v-model="target"
        name="toStatus"
        :label="t('requests.overrides.target')"
        :options="options"
        :error="errors.toStatus"
        required
      />
      <BaseTextarea
        v-model="reason"
        name="reason"
        :label="t('requests.overrides.reason')"
        :hint="t('entities.reason.hint', { min: ACCREDITED_LIMITS.OVERRIDE_REASON_MIN })"
        :error="errors.reason"
        required
      />
    </div>
  </FormModal>
</template>
