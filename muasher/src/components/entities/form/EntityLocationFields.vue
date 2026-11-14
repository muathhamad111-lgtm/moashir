<script setup>
import { useI18n } from 'vue-i18n'
import FieldSelect from '@/components/form/FieldSelect.vue'
import { useRegionCityFields } from '@/composables/useRegionCityFields'

const props = defineProps({
  regionOptions: { type: Array, required: true },
  cityOptionsFor: { type: Function, required: true },
})

const { t } = useI18n()
const { regionId, cityOptions } = useRegionCityFields(props.cityOptionsFor)
</script>

<template>
  <fieldset class="entity-form__group">
    <legend class="entity-form__legend">{{ t('entities.sections.location') }}</legend>
    <div class="entity-form__grid">
      <FieldSelect
        name="regionId"
        :label="t('entities.fields.region')"
        :options="regionOptions"
        required
      />
      <FieldSelect
        name="cityId"
        :label="t('entities.fields.city')"
        :options="cityOptions"
        :disabled="!regionId"
        required
      />
    </div>
  </fieldset>
</template>
