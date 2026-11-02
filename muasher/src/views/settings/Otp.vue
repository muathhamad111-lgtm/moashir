<script setup>
import { computed, onMounted, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useSettingsSection } from '@/composables/useSettingsSection'
import { useToast } from '@/composables/useToast'
import { settingsService } from '@/services/settingsService'

const { t } = useI18n()
const toast = useToast()

const form = reactive({ emailOtp: true, smsOtp: false, emailPassword: true })

const { data, status, errorMessage, saving, load, save } = useSettingsSection({
  fetcher: () => settingsService.getOtp(),
  saver: (payload) => settingsService.updateOtp(payload),
})

watch(data, (value) => {
  if (!value) return
  form.emailOtp = Boolean(value.emailOtp)
  form.smsOtp = Boolean(value.smsOtp)
  form.emailPassword = Boolean(value.emailPassword)
})

onMounted(load)

const noneEnabled = computed(() => !form.emailOtp && !form.smsOtp && !form.emailPassword)

function onSave() {
  if (noneEnabled.value) {
    toast.error(t('settings.otp.atLeastOne'))
    return
  }
  save({ ...form })
}
</script>

<template>
  <SettingsSection
    :title="t('settings.otp.title')"
    :description="t('settings.otp.description')"
    :status="status"
    :error-message="errorMessage"
    @retry="load"
  >
    <div class="settings-form">
      <div class="settings-form__toggles">
        <ToggleSwitch
          v-model="form.emailPassword"
          name="emailPassword"
          :label="t('settings.otp.emailPassword')"
          :description="t('settings.otp.emailPasswordDesc')"
        />
        <ToggleSwitch
          v-model="form.emailOtp"
          name="emailOtp"
          :label="t('settings.otp.emailOtp')"
          :description="t('settings.otp.emailOtpDesc')"
        />
        <ToggleSwitch
          v-model="form.smsOtp"
          name="smsOtp"
          :label="t('settings.otp.smsOtp')"
          :description="t('settings.otp.smsOtpDesc')"
        />
      </div>

      <p v-if="noneEnabled" class="settings-form__warning" role="alert">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        {{ t('settings.otp.atLeastOne') }}
      </p>
    </div>

    <template #actions>
      <BaseButton
        variant="primary"
        :loading="saving"
        :disabled="noneEnabled"
        icon="fa-solid fa-floppy-disk"
        @click="onSave"
      >
        {{ t('actions.save') }}
      </BaseButton>
    </template>
  </SettingsSection>
</template>
