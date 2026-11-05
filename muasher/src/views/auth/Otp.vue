<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import OtpInput from '@/components/form/OtpInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AuthFailureAlert from '@/components/auth/AuthFailureAlert.vue'
import OtpChallengeEnded from '@/components/auth/OtpChallengeEnded.vue'
import { useOtpStep } from '@/composables/useOtpStep'
import { useLoginOutcome } from '@/composables/useLoginOutcome'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { SIGN_IN_METHODS } from '@/constants/signIn'

const { t } = useI18n()
const router = useRouter()
const step = useOtpStep()
const { signInRoute } = useLoginOutcome()
const {
  otp,
  code,
  codeError,
  failure,
  submitting,
  resending,
  isCode,
  destination,
  channelIcon,
  ended,
  locked,
} = step

onMounted(() => {
  if (!step.start()) router.replace({ name: ROUTE_NAMES.AUTH_LOGIN })
})
</script>

<template>
  <section class="auth-form auth-otp">
    <header class="auth-form__header">
      <span v-if="isCode" class="auth-otp__tile" :data-channel="otp.channel.value">
        <AppIcon :name="channelIcon" />
      </span>
      <h1 class="auth-form__title">{{ t('auth.otp.title') }}</h1>
      <i18n-t
        v-if="destination.inline"
        :keypath="destination.subtitleKey"
        scope="global"
        tag="p"
        class="auth-form__subtitle"
      >
        <template #target>
          <bdi class="auth-otp__target" dir="ltr">{{ destination.target }}</bdi>
        </template>
      </i18n-t>
      <template v-else>
        <p class="auth-form__subtitle">{{ t(destination.subtitleKey) }}</p>
        <p v-if="destination.target" class="auth-otp__target-line">
          <bdi class="auth-otp__target" dir="ltr">{{ destination.target }}</bdi>
        </p>
      </template>
      <p v-if="!ended" class="auth-form__hint">
        {{
          t('auth.otp.validity', { count: otp.validityMinutes.value }, otp.validityMinutes.value)
        }}
      </p>
    </header>

    <OtpChallengeEnded
      v-if="isCode && ended"
      :failure="failure"
      :request-route="signInRoute(SIGN_IN_METHODS.CODE)"
    />
    <AuthFailureAlert v-else-if="failure" :failure="failure" group="code" />

    <div v-if="!ended" class="auth-form__body">
      <OtpInput
        v-model="code"
        :length="otp.length.value"
        :label="t('auth.otp.inputLabel')"
        :invalid="Boolean(codeError)"
        :disabled="locked"
        @complete="step.verify"
      />

      <p v-if="codeError" class="auth-otp__error" role="alert">{{ codeError }}</p>

      <p class="auth-otp__expiry" :class="{ 'is-expired': otp.expired.value }" aria-live="polite">
        <AppIcon :name="otp.expired.value ? 'x' : 'clock'" />
        <span v-if="otp.expired.value">{{ t('auth.otp.expired') }}</span>
        <span v-else>{{ t('auth.otp.expiresIn', { time: otp.expiryClock.value }) }}</span>
      </p>

      <div class="auth-form__actions">
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

      <div class="auth-otp__resend">
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
      <router-link v-if="isCode" class="auth-form__link auth-otp__password" :to="signInRoute()">
        <AppIcon name="lock" />
        {{ t('auth.otp.code.usePassword') }}
      </router-link>
      <router-link v-else class="auth-form__link" :to="{ name: ROUTE_NAMES.AUTH_LOGIN }">
        {{ t('auth.otp.backToLogin') }}
      </router-link>
    </p>
  </section>
</template>
