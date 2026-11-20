<script setup>
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import NationalIdField from '@/components/identity/NationalIdField.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { identityChangePayload, identityChangeSchema } from '@/utils/identity/identityChangeSchema'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  action: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

const EMPTY = { newName: '', newNationalId: '', reason: '', currentPassword: '' }

const form = useValidatedForm({
  schema: identityChangeSchema(t),
  initialValues: { ...EMPTY },
  action: 'nationalIdentity.change.submit',
  submit: async (values) => {
    const saved = await props.action(identityChangePayload(values))
    if (saved) emit('update:modelValue', false)
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: { ...EMPTY } })
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="t('nationalIdentity.change.title')"
    :subtitle="t('nationalIdentity.change.subtitle')"
    :busy="form.submitting.value"
    :submit-label="t('nationalIdentity.change.submit')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="identity-change-form" data-testid="identity-change-form">
      <FieldInput
        name="newName"
        :label="t('nationalIdentity.change.newName')"
        autocomplete="name"
      />
      <NationalIdField
        name="newNationalId"
        :label="t('nationalIdentity.change.newNationalId')"
        :required="false"
      />
      <FieldInput
        v-if="form.values.newNationalId"
        name="currentPassword"
        type="password"
        autocomplete="current-password"
        :label="t('nationalIdentity.change.currentPassword')"
        required
      />
      <FieldTextarea name="reason" :label="t('nationalIdentity.change.reason')" required />
    </div>
  </FormModal>
</template>
