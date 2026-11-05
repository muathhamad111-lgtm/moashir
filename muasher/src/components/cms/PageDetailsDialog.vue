<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import LocalizedFields from './LocalizedFields.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { pageDetailsSchema } from '@/utils/cmsSchemas'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  page: { type: Object, default: null },
  save: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

function bilingual(value) {
  return { ar: value?.ar ?? '', en: value?.en ?? '' }
}

function initialValues() {
  return {
    title: bilingual(props.page?.title),
    seo: {
      title: bilingual(props.page?.seo?.title),
      description: bilingual(props.page?.seo?.description),
    },
  }
}

const form = useValidatedForm({
  schema: computed(() => pageDetailsSchema(t)),
  initialValues: initialValues(),
  action: 'cms.page.details',
  submit: async (values) => {
    await props.save(values)
    emit('update:modelValue', false)
  },
})

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
    :title="t('cms.page.detailsTitle')"
    :submit-label="t('actions.save')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="cms-form">
      <fieldset class="cms-form__group">
        <legend class="cms-form__legend">{{ t('cms.page.titleGroup') }}</legend>
        <LocalizedFields name="title" :label="t('cms.page.title')" />
      </fieldset>
      <fieldset class="cms-form__group">
        <legend class="cms-form__legend">{{ t('cms.page.seoGroup') }}</legend>
        <LocalizedFields name="seo.title" :label="t('cms.page.seoTitle')" />
        <LocalizedFields name="seo.description" :label="t('cms.page.seoDescription')" multiline />
      </fieldset>
    </div>
  </FormModal>
</template>
