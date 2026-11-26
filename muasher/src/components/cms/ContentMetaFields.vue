<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldSelect from '@/components/form/FieldSelect.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldCheckboxGroup from '@/components/form/FieldCheckboxGroup.vue'
import LocalizedFields from './LocalizedFields.vue'
import { metaFieldsFor } from '@/constants/cmsMetaFields'

const props = defineProps({
  sectionType: { type: String, required: true },
  forItem: { type: Boolean, default: false },
})

const { t } = useI18n()
const fields = computed(() => metaFieldsFor(props.sectionType, props.forItem))

function labelOf(field) {
  return t(`cms.metaFields.${field.key}`)
}

function optionsOf(field) {
  return field.options.map((value) => ({
    value,
    label: t(`cms.metaOptions.${field.key}.${value}`),
  }))
}

function choiceOptions(field) {
  return [{ value: '', label: t('cms.display.none') }, ...optionsOf(field)]
}
</script>

<template>
  <fieldset v-if="fields.length" class="cms-form__group" data-cms-meta-fields>
    <legend class="cms-form__legend">{{ t('cms.groups.sectionContent') }}</legend>
    <template v-for="field in fields" :key="field.key">
      <LocalizedFields
        v-if="field.kind === 'localized'"
        :name="`metaFields.${field.key}`"
        :label="labelOf(field)"
        :multiline="field.multiline"
      />
      <FieldSelect
        v-else-if="field.kind === 'choice'"
        :name="`metaFields.${field.key}`"
        :label="labelOf(field)"
        :options="choiceOptions(field)"
      />
      <FieldCheckboxGroup
        v-else-if="field.kind === 'choices'"
        :name="`metaFields.${field.key}`"
        :label="labelOf(field)"
        :options="optionsOf(field)"
      />
      <FieldInput
        v-else
        :name="`metaFields.${field.key}`"
        type="number"
        inputmode="numeric"
        input-dir="ltr"
        :label="labelOf(field)"
        :hint="t('cms.metaFields.rangeHint', { min: field.min, max: field.max })"
      />
    </template>
  </fieldset>
</template>
