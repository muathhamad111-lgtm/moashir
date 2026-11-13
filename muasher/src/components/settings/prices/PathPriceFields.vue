<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldSelect from '@/components/form/FieldSelect.vue'
import FieldDate from '@/components/form/FieldDate.vue'
import { PRICE_AVAILABILITY_VALUES } from '@/constants/prices'

defineProps({
  limits: { type: Object, required: true },
})

const { t } = useI18n()
const availabilityOptions = computed(() =>
  PRICE_AVAILABILITY_VALUES.map((value) => ({
    value,
    label: t(`settings.prices.availability.${value}`),
  })),
)
</script>

<template>
  <div class="settings-form__grid">
    <FieldInput
      name="priceBeforeVat"
      :label="t('settings.prices.fields.price')"
      :hint="t('settings.prices.fields.priceHint')"
      inputmode="decimal"
      input-dir="ltr"
      required
    />
    <FieldSelect
      name="availability"
      :label="t('settings.prices.fields.availability')"
      :options="availabilityOptions"
      required
    />
    <FieldDate
      name="effectiveFrom"
      :label="t('settings.prices.fields.effectiveFrom')"
      :hint="t('settings.prices.fields.effectiveFromHint')"
      :min="limits.effectiveFromMin"
      :max="limits.effectiveFromMax"
    />
  </div>
</template>
