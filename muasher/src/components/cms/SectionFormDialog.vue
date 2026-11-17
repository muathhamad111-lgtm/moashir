<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldSelect from '@/components/form/FieldSelect.vue'
import ContentFormFields from './ContentFormFields.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { CMS_SECTION_TYPES } from '@/constants/cms'
import { contentFormValues, contentPayload } from '@/utils/cmsPayload'
import { contentSchema, newSectionSchema } from '@/utils/cmsSchemas'
import { metaFieldsFor } from '@/constants/cmsMetaFields'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  section: { type: Object, default: null },
  save: { type: Function, required: true },
  upload: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

const isCreate = computed(() => !props.section)
const typeOptions = computed(() =>
  CMS_SECTION_TYPES.map((type) => ({ value: type, label: t(`cms.sectionTypes.${type}`) })),
)

function initialValues() {
  return { key: '', type: DEFAULT_TYPE, ...contentFormValues(props.section) }
}

const DEFAULT_TYPE = 'rich_text'
const chosenType = ref(DEFAULT_TYPE)
const currentType = computed(() => (isCreate.value ? chosenType.value : props.section.type))
const metaFields = computed(() => metaFieldsFor(currentType.value ?? '', false))

const form = useValidatedForm({
  schema: computed(() =>
    isCreate.value ? newSectionSchema(t, metaFields.value) : contentSchema(t, metaFields.value),
  ),
  initialValues: initialValues(),
  action: 'cms.section.save',
  submit: async (values) => {
    const payload = contentPayload(values, props.section, {
      withBackground: true,
      metaFields: metaFields.value,
    })
    await props.save(
      isCreate.value ? { ...payload, key: values.key.trim(), type: values.type } : payload,
    )
    emit('update:modelValue', false)
  },
})

const sectionType = computed(() => currentType.value ?? '')

watch(
  () => form.values.type,
  (type) => {
    chosenType.value = type || DEFAULT_TYPE
  },
)

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: initialValues() })
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    size="lg"
    :busy="form.submitting.value"
    :title="
      isCreate ? t('cms.section.createTitle') : t('cms.section.editTitle', { key: section.key })
    "
    :submit-label="isCreate ? t('cms.section.create') : t('actions.save')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <fieldset v-if="isCreate" class="cms-form__group">
      <legend class="cms-form__legend">{{ t('cms.groups.identity') }}</legend>
      <div class="cms-form__grid">
        <FieldInput
          name="key"
          :label="t('cms.fields.key')"
          :hint="t('cms.fields.keyHint')"
          input-dir="ltr"
          required
        />
        <FieldSelect name="type" :label="t('cms.fields.type')" :options="typeOptions" required />
      </div>
    </fieldset>
    <ContentFormFields
      :section-type="sectionType"
      :upload="upload"
      :disabled="form.submitting.value"
    />
  </FormModal>
</template>
