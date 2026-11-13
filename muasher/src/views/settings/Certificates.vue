<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldToggle from '@/components/form/FieldToggle.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import CertificateLevelRanges from '@/components/settings/CertificateLevelRanges.vue'
import { useCertificateSettings } from '@/composables/useCertificateSettings'
import { CERTIFICATE_LEVELS } from '@/constants/certificates'
import { levelRangesOf } from '@/utils/settings/certificateSettingsForm'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const { status, errorMessage, settings, form, saving, reasonOpen, load, submit, confirmReason } =
  useCertificateSettings()

const ranges = computed(() => levelRangesOf(form.values.levelThresholds))

onMounted(load)
</script>

<template>
  <SettingsSection
    :title="t('settings.certificates.title')"
    :description="t('settings.certificates.description')"
    :status="status"
    :error-message="errorMessage"
    @retry="load"
  >
    <form
      v-if="settings"
      id="certificate-settings-form"
      class="settings-form"
      novalidate
      @submit.prevent="submit"
    >
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">
          {{ t('settings.certificates.issuanceTitle') }}
        </legend>
        <FieldToggle
          name="accreditedIssuanceEnabled"
          :label="t('settings.certificates.issuance')"
          :description="t('settings.certificates.issuanceDesc')"
        />
      </fieldset>
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.certificates.levelsTitle') }}</legend>
        <p class="settings-form__note" role="note">
          <AppIcon name="info-circle" />
          {{ t('settings.certificates.levelsNote') }}
        </p>
        <div class="settings-form__grid">
          <FieldInput
            v-for="level in CERTIFICATE_LEVELS"
            :key="level"
            :name="`levelThresholds.${level}`"
            :label="
              t('settings.certificates.minimum', {
                level: t(`settings.certificates.levels.${level}`),
              })
            "
            :hint="t('settings.certificates.minimumHint')"
            inputmode="decimal"
            input-dir="ltr"
            required
          />
        </div>
        <CertificateLevelRanges :ranges="ranges" />
      </fieldset>
    </form>
    <template #actions>
      <BaseButton
        v-if="settings"
        type="submit"
        form="certificate-settings-form"
        variant="primary"
        :loading="saving"
        icon="save"
        >{{ t('actions.save') }}</BaseButton
      >
    </template>
  </SettingsSection>
  <ReasonDialog
    v-model="reasonOpen"
    :title="t('settings.assessment.reasonTitle')"
    :submit-label="t('actions.save')"
    :action="confirmReason"
    action-name="settings.certificates.update"
  />
</template>
