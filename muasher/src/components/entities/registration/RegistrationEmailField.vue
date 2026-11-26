<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import FormField from '@/components/form/FormField.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import OtpInput from '@/components/form/OtpInput.vue'
import {
  EMAIL_CHECK_STATES as STATES,
  useRegistrationEmail,
} from '@/composables/useRegistrationEmail'

const { t } = useI18n()
const check = useRegistrationEmail()
const { email, emailError, state, code, error, busy, otp, tokenError } = check
const errorId = useId()
const emailText = computed(() => String(email.value ?? ''))

function onInput(event) {
  email.value = event.target.value
}
</script>

<template>
  <div class="email-check" :data-state="state" data-testid="registration-email-check">
    <FormField
      v-slot="{ id, describedBy, invalid }"
      name="officialEmail"
      :label="t('entities.fields.officialEmail')"
      :error="emailError"
      :filled="emailText.length > 0"
      required
    >
      <div class="email-check__field">
        <input
          :id="id"
          class="field__control"
          type="email"
          name="officialEmail"
          :value="emailText"
          placeholder=" "
          autocomplete="email"
          dir="ltr"
          required
          :aria-invalid="invalid || undefined"
          :aria-describedby="describedBy"
          @input="onInput"
          @blur="check.blurEmail"
        />
        <div class="email-check__inline">
          <BaseButton
            v-if="state === STATES.IDLE"
            class="email-check__send"
            variant="primary"
            size="sm"
            :loading="busy"
            data-testid="email-check-send"
            @click="check.send"
          >
            {{ t('entities.registration.emailVerify.send') }}
          </BaseButton>
          <span v-else-if="state === STATES.SENT" class="email-check__badge" role="status">
            <AppIcon name="envelope" />
            {{ t('entities.registration.emailVerify.codeSent') }}
          </span>
          <span
            v-else
            class="email-check__badge email-check__badge--done"
            role="status"
            data-testid="email-check-done"
          >
            <AppIcon name="check-circle" />
            {{ t('entities.registration.emailVerify.done') }}
          </span>
        </div>
      </div>
    </FormField>

    <div v-if="state === STATES.SENT" class="email-check__code">
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

    <p v-if="error" :id="errorId" class="field__error" role="alert">{{ error }}</p>
    <p v-else-if="tokenError && state !== STATES.VERIFIED" class="field__error" role="alert">
      {{ tokenError }}
    </p>
  </div>
</template>
