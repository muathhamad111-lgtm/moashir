<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldCheckboxGroup from '@/components/form/FieldCheckboxGroup.vue'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  settings: { type: Object, required: true },
})

const { t } = useI18n()

const typeOptions = computed(() =>
  props.settings.evidenceTypeCatalog.map((value) => ({
    value,
    label: t(`settings.assessment.types.${value}`),
  })),
)
const recomputesDueDates = computed(() => props.settings.path === ASSESSMENT_PATHS.ACCREDITED)
</script>

<template>
  <fieldset class="settings-form__group">
    <legend class="settings-form__legend">{{ t('settings.assessment.deadlinesTitle') }}</legend>
    <p v-if="recomputesDueDates" class="settings-form__note" role="note">
      <AppIcon name="info-circle" />
      {{ t('settings.assessment.slaNote') }}
    </p>
    <div class="settings-form__grid">
      <FieldInput
        name="reviewSlaDays"
        :label="t('settings.paths.reviewSlaDays')"
        :hint="t('settings.assessment.reviewSlaDaysHint', settings.reviewSlaLimits)"
        inputmode="numeric"
        input-dir="ltr"
        required
      />
    </div>
  </fieldset>
  <fieldset class="settings-form__group">
    <legend class="settings-form__legend">{{ t('settings.assessment.uploadsTitle') }}</legend>
    <p class="settings-form__note" role="note">
      <AppIcon name="info-circle" />
      {{ t('settings.paths.uploadsNote') }}
    </p>
    <div class="settings-form__grid">
      <FieldInput
        name="uploadMaxFileMb"
        :label="t('settings.assessment.maxFileMb')"
        :hint="
          t('settings.assessment.rangeHint', { min: 1, max: settings.uploadLimitBounds.maxFileMb })
        "
        inputmode="numeric"
        input-dir="ltr"
        required
      />
      <FieldInput
        name="evidenceMaxFilesPerSub"
        :label="t('settings.assessment.maxFilesPerSub')"
        :hint="
          t('settings.assessment.rangeHint', {
            min: 1,
            max: settings.uploadLimitBounds.maxFilesPerSub,
          })
        "
        inputmode="numeric"
        input-dir="ltr"
        required
      />
    </div>
    <FieldCheckboxGroup
      name="evidenceAllowedTypes"
      :label="t('settings.assessment.allowedTypes')"
      :options="typeOptions"
      :hint="t('settings.assessment.allowedTypesHint')"
    />
  </fieldset>
</template>
