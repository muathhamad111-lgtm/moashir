<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldSelect from '@/components/form/FieldSelect.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { regionsService } from '@/services/regionsService'
import { citySchema } from '@/utils/entitySchemas'
import { toWesternDigits } from '@/utils/numerals'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  city: { type: Object, default: null },
  regionOptions: { type: Array, required: true },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()

function valuesOf(city) {
  return {
    regionId: city ? String(city.regionId) : '',
    name: { ar: city?.name.ar ?? '', en: city?.name.en ?? '' },
    sortOrder: city ? String(city.sortOrder) : '',
  }
}

function payloadOf(values) {
  const order = toWesternDigits(values.sortOrder)
  return {
    name: { ar: values.name.ar.trim(), en: values.name.en.trim() },
    sortOrder: order === '' ? null : Number(order),
  }
}

const form = useValidatedForm({
  schema: computed(() => citySchema(t)),
  initialValues: valuesOf(props.city),
  action: 'regions.saveCity.submit',
  submit: async (values) => {
    const payload = payloadOf(values)
    const { data } = props.city
      ? await regionsService.updateCity(props.city.id, payload)
      : await regionsService.createCity(Number(values.regionId), payload)
    emit('update:modelValue', false)
    emit('saved', data)
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: valuesOf(props.city) })
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="t(city ? 'entities.cities.editTitle' : 'entities.cities.createTitle')"
    :busy="form.submitting.value"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="entity-form">
      <FieldSelect
        name="regionId"
        :label="t('entities.fields.region')"
        :options="regionOptions"
        :disabled="Boolean(city)"
        required
      />
      <FieldInput name="name.ar" input-dir="rtl" :label="t('entities.cities.nameAr')" required />
      <FieldInput name="name.en" input-dir="ltr" :label="t('entities.cities.nameEn')" required />
      <FieldInput
        name="sortOrder"
        inputmode="numeric"
        :label="t('entities.cities.sortOrder')"
        :hint="t('entities.cities.sortOrderHint')"
      />
    </div>
  </FormModal>
</template>
