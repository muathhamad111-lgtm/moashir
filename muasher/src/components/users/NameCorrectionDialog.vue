<script setup>
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { object, string } from 'yup'
import FormModal from '@/components/modal/FormModal.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { IDENTITY_CHANGE_RULES as R } from '@/utils/identity/identityChangeSchema'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  user: { type: Object, default: null },
  action: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

const lengthMessage = (min, max) => t('nationalIdentity.change.reasonLength', { min, max })

const form = useValidatedForm({
  schema: object({
    newName: string()
      .trim()
      .required(t('validation.required'))
      .min(
        R.NAME_MIN,
        t('nationalIdentity.change.nameLength', { min: R.NAME_MIN, max: R.NAME_MAX }),
      )
      .max(
        R.NAME_MAX,
        t('nationalIdentity.change.nameLength', { min: R.NAME_MIN, max: R.NAME_MAX }),
      ),
    reason: string()
      .trim()
      .required(t('validation.required'))
      .min(R.REASON_MIN, lengthMessage(R.REASON_MIN, R.REASON_MAX))
      .max(R.REASON_MAX, lengthMessage(R.REASON_MIN, R.REASON_MAX)),
  }),
  initialValues: { newName: '', reason: '' },
  action: 'nationalIdentity.correction.submit',
  submit: async (values) => {
    const saved = await props.action({
      newName: values.newName.trim(),
      reason: values.reason.trim(),
    })
    if (saved) emit('update:modelValue', false)
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: { newName: props.user?.name ?? '', reason: '' } })
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="t('nationalIdentity.correction.title')"
    :subtitle="t('nationalIdentity.correction.subtitle')"
    :busy="form.submitting.value"
    :submit-label="t('nationalIdentity.correction.submit')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="identity-change-form">
      <FieldInput name="newName" :label="t('nationalIdentity.change.newName')" required />
      <FieldTextarea name="reason" :label="t('nationalIdentity.change.reason')" required />
    </div>
  </FormModal>
</template>
