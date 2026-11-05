<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldCheckboxGroup from '@/components/form/FieldCheckboxGroup.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import { useAssessmentSettings } from '@/composables/useAssessmentSettings'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { UPLOAD_BOUNDS } from '@/utils/accredited/assessmentSettingsForm'

const { t } = useI18n()
const { can } = usePermissions()
const { status, errorMessage, settings, limits, saving, reasonOpen, load, submit, confirmReason } =
  useAssessmentSettings()

const editable = computed(() => can(PERMISSIONS.SETTINGS_ASSESSMENT_MANAGE))
const typeOptions = computed(() =>
  (settings.value?.evidenceTypeCatalog ?? []).map((value) => ({
    value,
    label: t(`settings.assessment.types.${value}`),
    disabled: !editable.value,
  })),
)

onMounted(load)
</script>

<template>
  <SettingsSection
    :title="t('settings.assessment.title')"
    :description="t('settings.assessment.description')"
    :status="status"
    :error-message="errorMessage"
    @retry="load"
  >
    <form
      v-if="settings"
      id="assessment-settings-form"
      class="settings-form"
      novalidate
      @submit.prevent="submit"
    >
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.assessment.deadlinesTitle') }}</legend>
        <p class="settings-form__note" role="note">
          <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
          {{ t('settings.assessment.slaNote') }}
        </p>
        <div class="settings-form__grid">
          <FieldInput
            name="accreditedReviewSlaDays"
            :label="t('settings.assessment.reviewSlaDays')"
            :hint="t('settings.assessment.reviewSlaDaysHint', limits)"
            inputmode="numeric"
            input-dir="ltr"
            :disabled="!editable"
            required
          />
        </div>
      </fieldset>
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.assessment.uploadsTitle') }}</legend>
        <p class="settings-form__note" role="note">
          <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
          {{ t('settings.assessment.uploadsNote') }}
        </p>
        <div class="settings-form__grid">
          <FieldInput
            name="uploadMaxFileMb"
            :label="t('settings.assessment.maxFileMb')"
            :hint="t('settings.assessment.rangeHint', UPLOAD_BOUNDS.maxFileMb)"
            inputmode="numeric"
            input-dir="ltr"
            :disabled="!editable"
            required
          />
          <FieldInput
            name="evidenceMaxFilesPerSub"
            :label="t('settings.assessment.maxFilesPerSub')"
            :hint="t('settings.assessment.rangeHint', UPLOAD_BOUNDS.maxFilesPerSub)"
            inputmode="numeric"
            input-dir="ltr"
            :disabled="!editable"
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
    </form>
    <template #actions>
      <BaseButton
        v-if="editable && settings"
        type="submit"
        form="assessment-settings-form"
        variant="primary"
        :loading="saving"
        icon="fa-solid fa-floppy-disk"
        >{{ t('actions.save') }}</BaseButton
      >
    </template>
  </SettingsSection>
  <ReasonDialog
    v-model="reasonOpen"
    :title="t('settings.assessment.reasonTitle')"
    :message="t('settings.assessment.reasonMessage')"
    :submit-label="t('actions.save')"
    :action="confirmReason"
    action-name="settings.assessment.update"
  />
</template>
