<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldIconSelect from '@/components/form/FieldIconSelect.vue'
import FieldToggle from '@/components/form/FieldToggle.vue'
import LocalizedFields from '@/components/cms/LocalizedFields.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { KPI_ICON_OPTIONS } from '@/constants/kpis'
import { catalogFormValues, catalogPayload, kpiCatalogSchema } from '@/utils/kpiCatalogSchema'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  kind: { type: String, required: true },
  entry: { type: Object, default: null },
  save: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const isCategory = computed(() => props.kind === 'categories')

const form = useValidatedForm({
  schema: computed(() => kpiCatalogSchema(t, props.kind)),
  initialValues: catalogFormValues(props.entry),
  action: `kpis.catalog.${props.kind}.save`,
  submit: async (values) => {
    await props.save(catalogPayload(props.kind, values))
    emit('update:modelValue', false)
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: catalogFormValues(props.entry) })
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :busy="form.submitting.value"
    :title="t(`kpis.admin.catalog.${kind}.${entry ? 'editTitle' : 'createTitle'}`)"
    :submit-label="t('actions.save')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="cms-form">
      <FieldInput
        name="key"
        :label="t('kpis.admin.catalog.fields.key')"
        :hint="t('kpis.admin.catalog.fields.keyHint')"
        input-dir="ltr"
        required
      />
      <LocalizedFields name="name" :label="t('kpis.admin.catalog.fields.name')" required />
      <template v-if="isCategory">
        <LocalizedFields
          name="description"
          :label="t('kpis.admin.catalog.fields.description')"
          multiline
        />
        <FieldIconSelect
          name="icon"
          :label="t('cms.fields.icon')"
          :hint="t('cms.fields.iconHint')"
          :options="KPI_ICON_OPTIONS"
          optional
        />
      </template>
      <FieldInput
        name="order"
        type="number"
        inputmode="numeric"
        :label="t('kpis.admin.catalog.fields.order')"
        input-dir="ltr"
      />
      <FieldToggle
        name="active"
        :label="t('kpis.admin.catalog.fields.active')"
        :description="t('kpis.admin.catalog.fields.activeHint')"
      />
    </div>
  </FormModal>
</template>
