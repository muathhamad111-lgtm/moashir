<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useField } from 'vee-validate'
import { riyadhDateKey } from '@/utils/datetime'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldDate from '@/components/form/FieldDate.vue'
import EntityLocationFields from '@/components/entities/form/EntityLocationFields.vue'
import EntityClassificationFields from '@/components/entities/form/EntityClassificationFields.vue'
import EntityMetricsFields from '@/components/entities/form/EntityMetricsFields.vue'
import EntityUnifiedNumberField from '@/components/entities/form/EntityUnifiedNumberField.vue'

defineProps({
  regionOptions: { type: Array, required: true },
  cityOptionsFor: { type: Function, required: true },
})

const { t } = useI18n()
const { value: foundedOn } = useField('foundedOn')
const yearFollowsDate = computed(() => Boolean(foundedOn.value))
const today = riyadhDateKey(new Date())
</script>

<template>
  <div class="profile-fields">
    <fieldset class="entity-form__group">
      <legend class="entity-form__legend">{{ t('entities.sections.identity') }}</legend>
      <div class="entity-form__grid">
        <FieldInput name="name.ar" input-dir="rtl" :label="t('entities.fields.nameAr')" required />
        <FieldInput
          name="name.en"
          input-dir="ltr"
          :label="t('entities.fields.nameEn')"
          :hint="t('entities.hints.nameEn')"
        />
        <EntityUnifiedNumberField />
        <FieldInput
          name="licenseNumber"
          input-dir="ltr"
          inputmode="numeric"
          :label="t('portal.profile.fields.licenseNumber')"
          :hint="t('portal.profile.hints.licenseNumber')"
        />
        <FieldDate
          name="foundedOn"
          min="1900-01-01"
          :max="today"
          :label="t('portal.profile.fields.foundedOn')"
          :hint="t('portal.profile.hints.foundedOn')"
        />
        <FieldInput
          name="foundingYear"
          inputmode="numeric"
          input-dir="ltr"
          :label="t('entities.fields.foundingYear')"
          :hint="yearFollowsDate ? t('portal.profile.hints.yearFollowsDate') : ''"
          :readonly="yearFollowsDate"
          required
        />
      </div>
    </fieldset>
    <EntityLocationFields :region-options="regionOptions" :city-options-for="cityOptionsFor" />
    <EntityClassificationFields :show-founding-year="false" />
    <EntityMetricsFields :hint="t('entities.hints.metricsProfile')" />
  </div>
</template>
