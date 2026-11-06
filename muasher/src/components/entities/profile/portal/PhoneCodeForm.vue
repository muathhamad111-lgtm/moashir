<script setup>
import { useI18n } from 'vue-i18n'
import OtpInput from '@/components/form/OtpInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  otp: { type: Object, required: true },
  busy: { type: Boolean, default: false },
})

const code = defineModel('code', { type: String, default: '' })
const emit = defineEmits(['confirm', 'resend', 'cancel'])
const { t } = useI18n()
</script>

<template>
  <form class="phone-verification__form" novalidate @submit.prevent="emit('confirm', code)">
    <p class="phone-verification__sent">
      {{ t('entities.phone.sent', { count: otp.validityMinutes }, otp.validityMinutes) }}
    </p>
    <OtpInput
      v-model="code"
      :length="otp.length"
      :label="t('entities.phone.codeLabel')"
      :disabled="busy || otp.expired"
      @complete="emit('confirm', $event)"
    />
    <p class="auth-otp__expiry" :class="{ 'is-expired': otp.expired }" aria-live="polite">
      <AppIcon :name="otp.expired ? 'x' : 'clock'" />
      <span v-if="otp.expired">{{ t('auth.otp.expired') }}</span>
      <span v-else>{{ t('auth.otp.expiresIn', { time: otp.expiryClock }) }}</span>
    </p>
    <div class="phone-verification__actions">
      <BaseButton type="submit" variant="primary" size="sm" :loading="busy">
        {{ t('entities.phone.confirm') }}
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
      <span v-else class="auth-otp__cooldown">
        {{ t('auth.otp.resendIn', { seconds: otp.resendSecondsLeft }) }}
      </span>
      <BaseButton type="button" variant="ghost" size="sm" @click="emit('cancel')">
        {{ t('actions.cancel') }}
      </BaseButton>
    </div>
  </form>
</template>
