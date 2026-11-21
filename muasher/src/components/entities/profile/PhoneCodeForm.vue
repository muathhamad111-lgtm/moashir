<script setup>
import { useI18n } from 'vue-i18n'
import OtpInput from '@/components/form/OtpInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  otp: { type: Object, required: true },
  phone: { type: String, required: true },
  busy: { type: Boolean, default: false },
  errorText: { type: String, default: '' },
})

const code = defineModel('code', { type: String, default: '' })
const emit = defineEmits(['confirm', 'resend', 'cancel'])
const { t } = useI18n()
</script>

<template>
  <form
    class="phone-otp"
    novalidate
    data-testid="phone-otp"
    @submit.prevent="emit('confirm', code)"
  >
    <p class="phone-otp__title">
      <AppIcon name="shield-info" />
      <span>{{ t('portal.profile.phone.otpTitle') }}</span>
    </p>
    <p class="phone-otp__sent">
      {{ t('portal.profile.phone.otpSent') }}
      <bdi dir="ltr">{{ phone }}</bdi>
    </p>
    <OtpInput
      v-model="code"
      :length="otp.length"
      :label="t('entities.phone.codeLabel')"
      :invalid="Boolean(errorText)"
      :disabled="busy || otp.expired"
      @complete="emit('confirm', $event)"
    />
    <p v-if="errorText" class="phone-otp__error" role="alert">{{ errorText }}</p>
    <p v-else-if="otp.expired" class="phone-otp__note" role="status">
      {{ t('auth.otp.expired') }}
    </p>
    <div class="phone-otp__actions">
      <BaseButton type="submit" variant="primary" size="sm" :loading="busy">
        {{ t('portal.profile.phone.confirm') }}
      </BaseButton>
      <BaseButton
        v-if="otp.canResend"
        type="button"
        variant="ghost"
        size="sm"
        :disabled="busy"
        @click="emit('resend')"
      >
        {{ t('auth.otp.resend') }}
      </BaseButton>
      <span v-else class="phone-otp__cooldown">
        {{ t('auth.otp.resendIn', { seconds: otp.resendSecondsLeft }) }}
      </span>
      <BaseButton type="button" variant="ghost" size="sm" @click="emit('cancel')">
        {{ t('actions.cancel') }}
      </BaseButton>
    </div>
  </form>
</template>
