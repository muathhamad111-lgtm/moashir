<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import OtpInput from '@/components/form/OtpInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AuthFailureAlert from '@/components/auth/AuthFailureAlert.vue'
import { useOtpStep } from '@/composables/useOtpStep'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const router = useRouter()
const step = useOtpStep()
const { otp, code, codeError, failure, submitting, resending, target, ended, locked } = step

const subtitleKey = computed(() => {
  if (step.isRegistration.value) return 'auth.otp.registrationSubtitle'
  return target.value ? 'auth.otp.signInSubtitle' : 'auth.otp.signInSubtitleGeneric'
})

onMounted(() => {
  if (!step.start()) router.replace({ name: ROUTE_NAMES.AUTH_LOGIN })
})
</script>

<template>
  <section class="auth-form auth-otp">
    <header class="auth-form__header">
      <h1 class="auth-form__title">{{ t('auth.otp.title') }}</h1>
      <i18n-t :keypath="subtitleKey" scope="global" tag="p" class="auth-form__subtitle">
        <template #target>
          <bdi class="auth-otp__target" dir="ltr">{{ target }}</bdi>
        </template>
      </i18n-t>
      <p class="auth-form__hint">
        {{
          t('auth.otp.validity', { count: otp.validityMinutes.value }, otp.validityMinutes.value)
        }}
      </p>
    </header>

    <AuthFailureAlert v-if="failure" :failure="failure" group="code" />

    <div class="auth-form__body">
      <OtpInput
        v-model="code"
        :length="otp.length.value"
        :label="t('auth.otp.inputLabel')"
        :invalid="Boolean(codeError)"
        :disabled="locked"
        @complete="step.verify"
      />

      <p v-if="codeError" class="auth-otp__error" role="alert">{{ codeError }}</p>

      <p
        v-if="!ended"
        class="auth-otp__expiry"
        :class="{ 'is-expired': otp.expired.value }"
        aria-live="polite"
      >
        <AppIcon :name="otp.expired.value ? 'x' : 'clock'" />
        <span v-if="otp.expired.value">{{ t('auth.otp.expired') }}</span>
        <span v-else>{{ t('auth.otp.expiresIn', { time: otp.expiryClock.value }) }}</span>
      </p>

      <div v-if="!ended" class="auth-form__actions">
        <BaseButton
          type="button"
          variant="primary"
          :loading="submitting"
          :disabled="otp.expired.value"
          @click="step.verify()"
        >
          {{ t('auth.otp.verify') }}
        </BaseButton>
      </div>

      <div v-if="!ended" class="auth-otp__resend">
        <span>{{ t('auth.otp.noCode') }}</span>
        <button
          v-if="otp.canResend.value"
          type="button"
          class="auth-form__link"
          :disabled="resending"
          @click="step.resend"
        >
          {{ t('auth.otp.resend') }}
        </button>
        <span v-else class="auth-otp__cooldown">
          {{ t('auth.otp.resendIn', { seconds: otp.resendSecondsLeft.value }) }}
        </span>
      </div>
    </div>

    <p class="auth-form__switch">
      <router-link class="auth-form__link" :to="{ name: ROUTE_NAMES.AUTH_LOGIN }">
        {{ t('auth.otp.backToLogin') }}
      </router-link>
    </p>
  </section>
</template>
