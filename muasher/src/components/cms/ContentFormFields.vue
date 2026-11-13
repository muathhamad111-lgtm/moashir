<script setup>
import { useI18n } from 'vue-i18n'
import { useField } from 'vee-validate'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldIconSelect from '@/components/form/FieldIconSelect.vue'
import { PICKABLE_ICON_NAMES } from '@/utils/icons/iconRegistry'
import LocalizedFields from './LocalizedFields.vue'
import ContentImageField from './ContentImageField.vue'
import ContentDisplayFields from './ContentDisplayFields.vue'

defineProps({
  sectionType: { type: String, required: true },
  forItem: { type: Boolean, default: false },
  upload: { type: Function, required: true },
  disabled: { type: Boolean, default: false },
})

const { t } = useI18n()
const { value: image } = useField('image')
</script>

<template>
  <div class="cms-form">
    <fieldset class="cms-form__group">
      <legend class="cms-form__legend">{{ t('cms.groups.text') }}</legend>
      <LocalizedFields name="title" :label="t('cms.fields.title')" />
      <LocalizedFields name="subtitle" :label="t('cms.fields.subtitle')" />
      <LocalizedFields name="description" :label="t('cms.fields.description')" multiline />
    </fieldset>

    <fieldset class="cms-form__group">
      <legend class="cms-form__legend">{{ t('cms.groups.cta') }}</legend>
      <LocalizedFields name="ctaLabel" :label="t('cms.fields.ctaLabel')" />
      <FieldInput
        name="ctaUrl"
        :label="t('cms.fields.ctaUrl')"
        :hint="t('cms.fields.ctaUrlHint')"
        input-dir="ltr"
      />
    </fieldset>

    <fieldset class="cms-form__group">
      <legend class="cms-form__legend">{{ t('cms.groups.media') }}</legend>
      <div class="cms-form__grid">
        <FieldIconSelect
          name="icon"
          :label="t('cms.fields.icon')"
          :hint="t('cms.fields.iconHint')"
          :options="PICKABLE_ICON_NAMES"
          optional
        />
        <FieldInput
          v-if="!forItem"
          name="background"
          :label="t('cms.fields.background')"
          :hint="t('cms.fields.backgroundHint')"
          input-dir="ltr"
        />
      </div>
      <ContentImageField
        v-model="image"
        :upload="upload"
        :label="t('cms.fields.image')"
        name="contentImage"
        :disabled="disabled"
      />
    </fieldset>

    <ContentDisplayFields :section-type="sectionType" :for-item="forItem" />
  </div>
</template>
