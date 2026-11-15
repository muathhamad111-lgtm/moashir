<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldToggle from '@/components/form/FieldToggle.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import CertificateLevelRanges from '@/components/settings/CertificateLevelRanges.vue'
import { useCertificateSettings } from '@/composables/useCertificateSettings'
import { CERTIFICATE_LEVELS, CERTIFICATE_SETTING_PARTS as PARTS } from '@/constants/certificates'
import { levelRangesOf } from '@/utils/settings/certificateSettingsForm'

const props = defineProps({
  part: {
    type: String,
    required: true,
    validator: (value) => Object.values(PARTS).includes(value),
  },
})

const { t } = useI18n()
const { status, errorMessage, settings, form, saving, reasonOpen, load, submit, confirmReason } =
  useCertificateSettings()
const ranges = computed(() => levelRangesOf(form.values.levelThresholds))
const formId = `certificate-settings-${props.part}`
const title = computed(() =>
  props.part === PARTS.ISSUANCE
    ? t('settings.paths.certificatesTitle')
    : t('settings.certificates.levelsTitle'),
)

onMounted(load)
</script>

<template>
  <section class="path-settings__card" :aria-label="title">
    <h2 class="path-settings__card-title">{{ title }}</h2>
    <Skeleton v-if="status === 'loading'" variant="text" :count="3" />
    <ErrorState v-else-if="status !== 'success'" :message="errorMessage" @retry="load" />
    <form
      v-else-if="settings"
      :id="formId"
      class="settings-form"
      novalidate
      @submit.prevent="submit"
    >
      <FieldToggle
        v-if="part === PARTS.ISSUANCE"
        name="accreditedIssuanceEnabled"
        :label="t('settings.certificates.issuance')"
        :description="t('settings.certificates.issuanceDesc')"
      />
      <template v-else>
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
            inputmode="decimal"
            input-dir="ltr"
            required
          />
        </div>
        <CertificateLevelRanges :ranges="ranges" />
      </template>
      <div class="path-settings__actions">
        <BaseButton type="submit" variant="primary" :loading="saving" icon="save">
          {{ t('actions.save') }}
        </BaseButton>
      </div>
    </form>
    <ReasonDialog
      v-model="reasonOpen"
      :title="t('settings.assessment.reasonTitle')"
      :submit-label="t('actions.save')"
      :action="confirmReason"
      :action-name="`settings.certificates.${part}.update`"
    />
  </section>
</template>
