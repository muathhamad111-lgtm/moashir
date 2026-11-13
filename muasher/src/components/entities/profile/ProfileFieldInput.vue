<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldDigits from '@/components/form/FieldDigits.vue'
import FieldDate from '@/components/form/FieldDate.vue'
import FieldSelect from '@/components/form/FieldSelect.vue'
import FieldCheckbox from '@/components/form/FieldCheckbox.vue'
import { useEntityOptions } from '@/composables/useEntityOptions'
import { riyadhDateKey } from '@/utils/datetime'
import { fieldLabelKey } from '@/utils/entityProfile/sections'
import { PROFILE_FIELD_INPUTS } from '@/constants/entityProfile'
import { UNIFIED_NUMBER_LENGTH } from '@/constants/entityEnums'

const EARLIEST_FOUNDING_DATE = '1900-01-01'

const props = defineProps({
  field: { type: Object, required: true },
  regionOptions: { type: Array, default: () => [] },
  cityOptions: { type: Array, default: () => [] },
})

const { t } = useI18n()
const options = useEntityOptions()
const today = riyadhDateKey(new Date())
const config = computed(() => PROFILE_FIELD_INPUTS[props.field.name] ?? { kind: 'text' })
const label = computed(() => t(fieldLabelKey(props.field.name)))
const hint = computed(() =>
  config.value.hint ? t(`portal.profile.hints.${config.value.hint}`) : '',
)

const OPTION_SOURCES = {
  entityType: () => options.entityTypeOptions.value,
  developmentSector: () => options.sectorOptions.value,
  orgSize: () => options.orgSizeOptions.value,
  strategicPlan: () => options.strategicPlanOptions.value,
  region: () => props.regionOptions,
  city: () => props.cityOptions,
}
const selectOptions = computed(() => OPTION_SOURCES[config.value.options]?.() ?? [])
</script>

<template>
  <FieldDigits
    v-if="config.kind === 'digits'"
    :name="field.name"
    :label="label"
    :hint="hint"
    :maxlength="UNIFIED_NUMBER_LENGTH"
    :required="field.required"
  />
  <FieldDate
    v-else-if="config.kind === 'date'"
    :name="field.name"
    :label="label"
    :hint="hint"
    :min="EARLIEST_FOUNDING_DATE"
    :max="today"
    :required="field.required"
  />
  <FieldSelect
    v-else-if="config.kind === 'select'"
    :name="field.name"
    :label="label"
    :hint="hint"
    :options="selectOptions"
    :placeholder="t('common.select')"
    :required="field.required"
  />
  <FieldCheckbox
    v-else-if="config.kind === 'checkbox'"
    class="profile-field-input--wide"
    :name="field.name"
    :label="t('portal.profile.consentLabel')"
  />
  <FieldInput
    v-else
    :name="field.name"
    :label="label"
    :hint="hint"
    :type="config.type ?? 'text'"
    :input-dir="config.inputDir"
    :inputmode="config.inputmode"
    :autocomplete="config.autocomplete"
    :required="field.required"
  />
</template>
