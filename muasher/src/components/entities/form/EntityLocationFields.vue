<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useField } from 'vee-validate'
import FieldSelect from '@/components/form/FieldSelect.vue'

const props = defineProps({
  regionOptions: { type: Array, required: true },
  cityOptionsFor: { type: Function, required: true },
})

const { t } = useI18n()
const { value: regionId } = useField('regionId')
const { value: cityId } = useField('cityId')

const cityOptions = computed(() => props.cityOptionsFor(regionId.value))

watch(regionId, () => {
  if (!cityOptions.value.some((option) => option.value === String(cityId.value))) cityId.value = ''
})
</script>

<template>
  <fieldset class="entity-form__group">
    <legend class="entity-form__legend">{{ t('entities.sections.location') }}</legend>
    <div class="entity-form__grid">
      <FieldSelect name="regionId" :label="t('entities.fields.region')" :options="regionOptions" required />
      <FieldSelect
        name="cityId"
        :label="t('entities.fields.city')"
        :options="cityOptions"
        :hint="regionId ? '' : t('entities.hints.regionFirst')"
        :disabled="!regionId"
        required
      />
    </div>
  </fieldset>
</template>
