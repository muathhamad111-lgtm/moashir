<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import KpiCard from './KpiCard.vue'
import KpiFormIdentity from './KpiFormIdentity.vue'
import KpiFormValues from './KpiFormValues.vue'
import KpiFormTexts from './KpiFormTexts.vue'
import KpiFormDisplay from './KpiFormDisplay.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { kpiFormValues, kpiPayload } from '@/utils/kpiPayload'
import { kpiSchema } from '@/utils/kpiSchemas'
import { localizedText } from '@/utils/localizedText'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  kpi: { type: Object, default: null },
  categories: { type: Array, default: () => [] },
  types: { type: Array, default: () => [] },
  save: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t, locale } = useI18n()

const form = useValidatedForm({
  schema: computed(() => kpiSchema(t)),
  initialValues: kpiFormValues(props.kpi),
  action: 'kpis.form.submit',
  submit: async (values) => {
    await props.save(kpiPayload(values, props.kpi))
    emit('update:modelValue', false)
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: kpiFormValues(props.kpi) })
  },
)

function localized(value) {
  return localizedText(value, locale.value)
}

const preview = computed(() => ({
  id: 'preview',
  ...form.values,
  cardColor: form.values.cardColorToken,
}))
</script>

<template>
  <FormModal
    :model-value="modelValue"
    size="lg"
    :busy="form.submitting.value"
    :title="kpi ? t('kpis.admin.form.editTitle') : t('kpis.admin.form.createTitle')"
    :submit-label="kpi ? t('actions.save') : t('kpis.admin.form.create')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="kpi-form">
      <div class="kpi-form__grid">
        <KpiFormIdentity :categories="categories" :types="types" :localized="localized" />
        <KpiFormValues :editing="Boolean(kpi)" />
        <KpiFormTexts />
        <KpiFormDisplay :disabled="form.submitting.value" />
      </div>
      <aside class="kpi-form__preview" aria-live="polite">
        <p class="kpi-form__preview-label">
          <AppIcon name="eye" />
          {{ t('kpis.admin.form.previewLabel') }}
        </p>
        <KpiCard :kpi="preview" :localized="localized" />
      </aside>
    </div>
  </FormModal>
</template>
