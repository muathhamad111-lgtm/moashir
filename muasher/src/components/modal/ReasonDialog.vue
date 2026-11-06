<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from './FormModal.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { optionalReasonSchema, reasonSchema } from '@/utils/entitySchemas'
import { REASON_LENGTH } from '@/constants/entityEnums'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  title: { type: String, required: true },
  message: { type: String, default: '' },
  submitLabel: { type: String, default: '' },
  reasonLabel: { type: String, default: '' },
  optional: { type: Boolean, default: false },
  action: { type: Function, required: true },
  actionName: { type: String, required: true },
  minLength: { type: Number, default: REASON_LENGTH.MIN },
})

const emit = defineEmits(['update:modelValue', 'done'])
const { t } = useI18n()

const form = useValidatedForm({
  schema: computed(() =>
    props.optional ? optionalReasonSchema(t) : reasonSchema(t, props.minLength),
  ),
  initialValues: { reason: '' },
  action: props.actionName,
  submit: async ({ reason }) => {
    const result = await props.action(reason.trim())
    emit('update:modelValue', false)
    emit('done', result)
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: { reason: '' } })
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="title"
    :busy="form.submitting.value"
    :submit-label="submitLabel"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="reason-dialog">
      <p v-if="message" class="reason-dialog__message">{{ message }}</p>
      <FieldTextarea
        name="reason"
        :label="reasonLabel || t('entities.reason.label')"
        :hint="
          optional
            ? t('entities.reason.optionalHint')
            : t('entities.reason.hint', { min: minLength })
        "
        :required="!optional"
      />
    </div>
  </FormModal>
</template>
