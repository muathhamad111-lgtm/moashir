<script setup>
import { computed } from 'vue'
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { OTP_CHANNELS, SMS_STATUSES } from '@/constants/signIn'
import { withSmsChannel } from '@/utils/settings/authSettingsForm'

const props = defineProps({
  smsStatus: { type: String, required: true },
  smsLocked: { type: Boolean, default: false },
})

const { t } = useI18n()
const { value, errorMessage } = useField('entitySignInCodeChannels')
const { value: generalEmail } = useField('generalEmailNotifications')

const smsChecked = computed(() => (value.value ?? []).includes(OTP_CHANNELS.SMS))
const smsUnavailable = computed(() => props.smsStatus === SMS_STATUSES.UNAVAILABLE)
const smsDescription = computed(() =>
  smsUnavailable.value ? t('settings.signIn.channels.smsUnavailable') : '',
)

function allowSms(allowed) {
  value.value = withSmsChannel(value.value, allowed)
}
</script>

<template>
  <fieldset class="sign-in-channels" :data-sms-status="smsStatus">
    <legend class="sign-in-channels__legend">{{ t('settings.signIn.channels.legend') }}</legend>
    <BaseCheckbox
      v-model="generalEmail"
      name="codeChannelEmail"
      :label="t('settings.signIn.channels.email')"
      :description="t('settings.signIn.channels.emailGeneral')"
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
  </fieldset>
</template>
