<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { object as yupObject, string as yupString } from 'yup'
import FormModal from '@/components/modal/FormModal.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useCriteria } from '@/composables/useCriteria'
import { renameFieldErrors } from '@/services/mappers/fieldErrors'
import { pairedText } from '@/utils/criteriaSchemas'
import { criteriaVersionOption } from '@/utils/criteriaVersionOption'
import { CRITERIA_NAME_MAX_LENGTH, CRITERIA_VERSION_STATUSES as STATUS } from '@/constants/criteria'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  versions: { type: Array, required: true },
  sourceId: { type: Number, default: null },
  create: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const { localized } = useCriteria()

const FIELD_ALIASES = {
  cloneFromVersionId: 'source',
  'label.ar': 'labelAr',
  'label.en': 'labelEn',
  label: 'labelAr',
}

const sources = computed(() =>
  props.versions
    .filter((version) => version.status !== STATUS.DRAFT)
    .map((version) => {
      const option = criteriaVersionOption(t, localized, version)
      return { ...option, value: String(option.value) }
    }),
)

const schema = computed(() =>
  yupObject({
    source: yupString().required(t('validation.required')),
    labelAr: pairedText(t, 'labelEn', CRITERIA_NAME_MAX_LENGTH),
    labelEn: pairedText(t, 'labelAr', CRITERIA_NAME_MAX_LENGTH),
  }),
)

function initialValues() {
  return { source: props.sourceId ? String(props.sourceId) : '', labelAr: '', labelEn: '' }
}

function payloadOf(values) {
  const hasLabel = values.labelAr.trim() || values.labelEn.trim()
  return {
    cloneFromVersionId: Number(values.source),
    label: hasLabel ? { ar: values.labelAr.trim(), en: values.labelEn.trim() } : null,
  }
}

const form = useValidatedForm({
  schema,
  initialValues: initialValues(),
  action: 'criteria.draftDialog.submit',
  submit: async (values) => {
    try {
      await props.create(payloadOf(values))
      emit('update:modelValue', false)
    } catch (error) {
      throw renameFieldErrors(error, FIELD_ALIASES)
    }
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: initialValues() })
  },
)

const [source] = form.defineField('source')
const [labelAr] = form.defineField('labelAr')
const [labelEn] = form.defineField('labelEn')
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :busy="form.submitting.value"
    :title="t('criteria.draft.dialogTitle')"
    :subtitle="t('criteria.draft.dialogSubtitle')"
    :submit-label="t('criteria.draft.create')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="criteria-form">
      <AppSelect
        v-model="source"
        name="source"
        :label="t('criteria.draft.source')"
        :options="sources"
        :error="form.errors.value.source"
        required
      />
      <div class="criteria-form__grid">
        <BaseInput
          v-model="labelAr"
          dir="rtl"
          name="labelAr"
          :label="t('criteria.fields.labelAr')"
          :error="form.errors.value.labelAr"
        />
        <BaseInput
          v-model="labelEn"
          dir="ltr"
          name="labelEn"
          :label="t('criteria.fields.labelEn')"
          :error="form.errors.value.labelEn"
        />
      </div>
    </div>
  </FormModal>
</template>
