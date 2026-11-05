<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import FieldSelect from '@/components/form/FieldSelect.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import EntityPickerField from './EntityPickerField.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { privacyRequestsService } from '@/services/privacyRequestsService'
import { privacyRequestSchema } from '@/utils/entitySchemas'
import { PRIVACY_DETAILS_LENGTH, PRIVACY_REQUEST_KINDS } from '@/constants/entityEnums'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
})

const emit = defineEmits(['update:modelValue', 'created'])

const EMPTY_VALUES = { entityId: '', kind: '', details: '' }

const { t } = useI18n()
const kindOptions = computed(() =>
  PRIVACY_REQUEST_KINDS.map((value) => ({
    value,
    label: t(`entities.enums.privacyKind.${value}`),
  })),
)

const form = useValidatedForm({
  schema: computed(() => privacyRequestSchema(t)),
  initialValues: { ...EMPTY_VALUES },
  action: 'privacyRequests.create.submit',
  submit: async (values) => {
    const { data } = await privacyRequestsService.create({
      entityId: Number(values.entityId),
      kind: values.kind,
      details: values.details.trim(),
    })
    emit('update:modelValue', false)
    emit('created', data)
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: { ...EMPTY_VALUES } })
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="t('entities.privacy.form.title')"
    :busy="form.submitting.value"
    :submit-label="t('entities.privacy.form.submit')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="entity-form">
      <EntityPickerField
        v-if="modelValue"
        :label="t('entities.privacy.form.entity')"
        :placeholder="t('entities.privacy.form.entitySearch')"
      />
      <FieldSelect
        name="kind"
        :label="t('entities.privacy.form.kind')"
        :options="kindOptions"
        required
      />
      <FieldTextarea
        name="details"
        :label="t('entities.privacy.form.details')"
        :hint="t('entities.privacy.form.detailsHint', { min: PRIVACY_DETAILS_LENGTH.MIN })"
        required
      />
    </div>
  </FormModal>
</template>
