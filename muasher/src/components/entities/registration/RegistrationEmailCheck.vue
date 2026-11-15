<script setup>
import { useId } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import OtpInput from '@/components/form/OtpInput.vue'
import {
  EMAIL_CHECK_STATES as STATES,
  useRegistrationEmail,
} from '@/composables/useRegistrationEmail'

const { t } = useI18n()
const check = useRegistrationEmail()
const { state, code, error, busy, otp, tokenError } = check
const errorId = useId()
</script>

<template>
  <div class="email-check" :data-state="state" data-testid="registration-email-check">
    <BaseButton
      v-if="state === STATES.IDLE"
      variant="secondary"
      size="sm"
      icon="envelope"
      :loading="busy"
      data-testid="email-check-send"
      @click="check.send"
    >
      {{ t('entities.registration.emailVerify.send') }}
    </BaseButton>

    <div v-else-if="state === STATES.SENT" class="email-check__code">
      <p class="email-check__hint">
        {{ t('entities.registration.emailVerify.sent', { minutes: otp.validityMinutes.value }) }}
      </p>
      <OtpInput
        v-model="code"
        :length="otp.length.value"
        :label="t('entities.registration.emailVerify.codeLabel')"
        :disabled="busy"
        :invalid="Boolean(error)"
        :described-by="error ? errorId : undefined"
        @complete="check.verify"
      />
      <div class="email-check__actions">
        <BaseButton
          variant="primary"
          size="sm"
          :loading="busy"
          data-testid="email-check-verify"
          @click="check.verify()"
        >
          {{ t('entities.registration.emailVerify.confirm') }}
        </BaseButton>
        <button
          type="button"
          class="email-check__link"
          :disabled="!otp.canResend.value || busy"
          @click="check.resend"
        >
          {{
            otp.canResend.value
              ? t('entities.registration.emailVerify.resend')
              : t('entities.registration.emailVerify.resendIn', {
                  seconds: otp.resendSecondsLeft.value,
                })
          }}
        </button>
      </div>
    </div>

    <p v-else class="email-check__done" role="status" data-testid="email-check-done">
      <AppIcon name="check-circle" />
      {{ t('entities.registration.emailVerify.done') }}
    </p>

    <p v-if="error" :id="errorId" class="field__error" role="alert">{{ error }}</p>
    <p v-else-if="tokenError && state !== STATES.VERIFIED" class="field__error" role="alert">
      {{ tokenError }}
    </p>
  </div>
</template>
