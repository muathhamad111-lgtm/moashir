<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldSelect from '@/components/form/FieldSelect.vue'
import LocalizedFields from './LocalizedFields.vue'
import { CMS_AVAILABILITY, CMS_ITEM_ROLES, CMS_SECTION_VARIANTS } from '@/constants/cms'

const props = defineProps({
  sectionType: { type: String, required: true },
  forItem: { type: Boolean, default: false },
})

const { t } = useI18n()

function withNone(values, labelKey) {
  return [
    { value: '', label: t('cms.display.none') },
    ...values.map((value) => ({ value, label: t(`${labelKey}.${value}`) })),
  ]
}

const availabilityOptions = computed(() =>
  withNone(CMS_AVAILABILITY, 'cms.display.availabilityOptions'),
)
const variants = computed(() =>
  props.forItem ? [] : (CMS_SECTION_VARIANTS[props.sectionType] ?? []),
)
const roles = computed(() => (props.forItem ? (CMS_ITEM_ROLES[props.sectionType] ?? []) : []))
</script>

<template>
  <fieldset class="cms-form__group">
    <legend class="cms-form__legend">{{ t('cms.groups.display') }}</legend>
    <LocalizedFields name="display.eyebrow" :label="t('cms.display.eyebrow')" />
    <div class="cms-form__grid">
      <FieldSelect
        name="display.availability"
        :label="t('cms.display.availability')"
        :options="availabilityOptions"
      />
      <FieldSelect
        v-if="variants.length"
        name="display.variant"
        :label="t('cms.display.variant')"
        :options="withNone(variants, 'cms.display.variantOptions')"
      />
      <FieldSelect
        v-if="roles.length"
        name="display.role"
        :label="t('cms.display.role')"
        :options="withNone(roles, 'cms.display.roleOptions')"
      />
    </div>
  </fieldset>
</template>
