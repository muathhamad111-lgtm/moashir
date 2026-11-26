<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import ContentFormFields from './ContentFormFields.vue'
import PartnerItemFields from './PartnerItemFields.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import {
  contentFormValues,
  contentPayload,
  partnerFormValues,
  partnerPayload,
} from '@/utils/cmsPayload'
import { contentSchema, partnerSchema } from '@/utils/cmsSchemas'
import { metaFieldsFor } from '@/constants/cmsMetaFields'
import { CMS_LOGO_SECTION_TYPES } from '@/constants/cms'

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
const isPartner = computed(() => CMS_LOGO_SECTION_TYPES.includes(props.sectionType))
const titleKey = computed(() => {
  const scope = isPartner.value ? 'cms.partners' : 'cms.items'
  return props.item ? `${scope}.editTitle` : `${scope}.addTitle`
})

function formValuesOf(item) {
  return isPartner.value ? partnerFormValues(item) : contentFormValues(item)
}

function payloadOf(values) {
  return isPartner.value
    ? partnerPayload(values, props.item)
    : contentPayload(values, props.item, { metaFields: metaFields.value })
}

const form = useValidatedForm({
  schema: computed(() => (isPartner.value ? partnerSchema(t) : contentSchema(t, metaFields.value))),
  initialValues: formValuesOf(props.item),
  action: 'cms.item.save',
  submit: async (values) => {
    await props.save(payloadOf(values))
    emit('update:modelValue', false)
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: formValuesOf(props.item) })
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    size="lg"
    :busy="form.submitting.value"
    :title="t(titleKey)"
    :submit-label="t('actions.save')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <PartnerItemFields v-if="isPartner" :upload="upload" :disabled="form.submitting.value" />
    <ContentFormFields
      v-else
      :section-type="sectionType"
      for-item
      :upload="upload"
      :disabled="form.submitting.value"
    />
  </FormModal>
</template>
