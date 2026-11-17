<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import ContentFormFields from './ContentFormFields.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { contentFormValues, contentPayload } from '@/utils/cmsPayload'
import { contentSchema } from '@/utils/cmsSchemas'
import { metaFieldsFor } from '@/constants/cmsMetaFields'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  item: { type: Object, default: null },
  sectionType: { type: String, required: true },
  save: { type: Function, required: true },
  upload: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

const metaFields = computed(() => metaFieldsFor(props.sectionType, true))

const form = useValidatedForm({
  schema: computed(() => contentSchema(t, metaFields.value)),
  initialValues: contentFormValues(props.item),
  action: 'cms.item.save',
  submit: async (values) => {
    await props.save(contentPayload(values, props.item, { metaFields: metaFields.value }))
    emit('update:modelValue', false)
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: contentFormValues(props.item) })
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    size="lg"
    :busy="form.submitting.value"
    :title="item ? t('cms.items.editTitle') : t('cms.items.addTitle')"
    :submit-label="t('actions.save')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <ContentFormFields
      :section-type="sectionType"
      for-item
      :upload="upload"
      :disabled="form.submitting.value"
    />
  </FormModal>
</template>
