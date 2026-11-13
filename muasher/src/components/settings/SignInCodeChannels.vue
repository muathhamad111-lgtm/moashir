<script setup>
import { computed } from 'vue'
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { OTP_CHANNELS, SMS_STATUSES } from '@/constants/signIn'
import { withSmsChannel } from '@/utils/settings/authSettingsForm'

const props = defineProps({
  smsStatus: { type: String, required: true },
  smsLocked: { type: Boolean, default: false },
})

const { t } = useI18n()
const { value, errorMessage } = useField('entitySignInCodeChannels')

const smsChecked = computed(() => (value.value ?? []).includes(OTP_CHANNELS.SMS))
const smsUnavailable = computed(() => props.smsStatus === SMS_STATUSES.UNAVAILABLE)
const smsInTestMode = computed(() => props.smsStatus === SMS_STATUSES.TEST)
const smsDescription = computed(() =>
  t(
    smsUnavailable.value
      ? 'settings.signIn.channels.smsUnavailable'
      : 'settings.signIn.channels.smsHelp',
  ),
)

function allowSms(allowed) {
  value.value = withSmsChannel(value.value, allowed)
}
</script>

<template>
  <fieldset class="sign-in-channels" :data-sms-status="smsStatus">
    <legend class="sign-in-channels__legend">{{ t('settings.signIn.channels.legend') }}</legend>
    <p class="sign-in-channels__help">{{ t('settings.signIn.channels.help') }}</p>
    <BaseCheckbox
      :model-value="true"
      name="codeChannelEmail"
      :label="t('settings.signIn.channels.email')"
      :description="t('settings.signIn.channels.emailAlways')"
      disabled
    />
    <BaseCheckbox
      :model-value="smsChecked"
      name="codeChannelSms"
      :label="t('settings.signIn.channels.sms')"
      :description="smsDescription"
      :error="errorMessage"
      :disabled="smsLocked"
      @update:model-value="allowSms"
    />
    <router-link
      v-if="smsUnavailable"
      class="sign-in-channels__setup"
      :to="{ name: ROUTE_NAMES.ADMIN_SETTINGS_SMS }"
    >
      {{ t('settings.signIn.channels.smsSetup') }}
    </router-link>
    <p v-if="smsInTestMode" class="sign-in-channels__warning" role="note">
      <AppIcon name="warning-triangle" />
      <span>{{ t('settings.signIn.channels.smsTest') }}</span>
    </p>
  </fieldset>
</template>
