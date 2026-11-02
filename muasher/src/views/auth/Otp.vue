<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import OtpInput from '@/components/form/OtpInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useAuthStore } from '@/stores/auth'
import { useAuthFlowStore } from '@/stores/authFlow'
import { useOtpChallenge } from '@/composables/useOtpChallenge'
import { useLoginOutcome } from '@/composables/useLoginOutcome'
import { useToast } from '@/composables/useToast'
import { logger } from '@/utils/logger'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const authFlow = useAuthFlowStore()
const toast = useToast()
const { continueAfter } = useLoginOutcome()

const otp = useOtpChallenge()
const code = ref('')
const codeError = ref('')
const submitting = ref(false)
const resending = ref(false)

onMounted(() => {
  if (!authFlow.hasChallenge) {
    router.replace({ name: ROUTE_NAMES.AUTH_LOGIN })
    return
  }
  otp.setChallenge(authFlow.challenge)
})

const maskedTarget = computed(() => authFlow.officialEmail || t('auth.otp.yourAccount'))
const channelLabel = computed(() => t(`auth.otp.channel.${otp.channel.value}`))

async function verify(value) {
  const entered = value ?? code.value
  if (submitting.value) return
  if (otp.expired.value) {
    codeError.value = t('auth.otp.errors.expired')
    return
  }
  if (entered.length !== otp.length.value) {
    codeError.value = t('auth.otp.errors.incomplete')
    return
  }
  submitting.value = true
  codeError.value = ''
  logger.breadcrumb('form', 'auth.otp.verify', { fields: ['challengeId', 'code'] })
  try {
    const outcome = await auth.verifyOtp({ challengeId: otp.challengeId.value, code: entered })
    otp.stop()
    await continueAfter(outcome)
  } catch (error) {
    logger.error(error, { action: 'auth.otp.verify', fields: ['challengeId', 'code'] })
    codeError.value =
      error?.normalized?.fieldErrors?.code?.[0] ||
      error?.normalized?.message ||
      t('auth.otp.errors.invalid')
    code.value = ''
  } finally {
    submitting.value = false
  }
}

async function resend() {
  if (resending.value || !otp.canResend.value) return
  resending.value = true
  codeError.value = ''
  try {
    const data = await auth.resendOtp({
      challengeId: otp.challengeId.value,
      channel: otp.channel.value,
    })
    authFlow.setChallenge({ challenge: data.otp })
    otp.setChallenge(data.otp)
    code.value = ''
    toast.success(t('auth.otp.resent'))
  } catch (error) {
    logger.error(error, { action: 'auth.otp.resend' })
    toast.error(error?.normalized?.message || t('auth.otp.errors.resendFailed'))
  } finally {
    resending.value = false
  }
}

function onComplete(value) {
  verify(value)
}
</script>

<template>
  <section class="auth-form auth-otp">
    <header class="auth-form__header">
      <h1 class="auth-form__title">{{ t('auth.otp.title') }}</h1>
      <p class="auth-form__subtitle">
        {{ t('auth.otp.subtitle', { channel: channelLabel, target: maskedTarget }) }}
      </p>
    </header>

    <div class="auth-form__body">
      <OtpInput
        v-model="code"
        :length="otp.length.value"
        :label="t('auth.otp.inputLabel')"
        :invalid="Boolean(codeError)"
        :disabled="submitting || otp.expired.value"
        @complete="onComplete"
      />

      <p v-if="codeError" class="auth-otp__error" role="alert">{{ codeError }}</p>

      <p class="auth-otp__expiry" :class="{ 'is-expired': otp.expired.value }" aria-live="polite">
        <i
          class="fa-regular"
          :class="otp.expired.value ? 'fa-circle-xmark' : 'fa-clock'"
          aria-hidden="true"
        ></i>
        <span v-if="otp.expired.value">{{ t('auth.otp.expired') }}</span>
        <span v-else>{{ t('auth.otp.expiresIn', { time: otp.expiryClock.value }) }}</span>
      </p>

      <div class="auth-form__actions">
        <BaseButton
          type="button"
          variant="primary"
          :loading="submitting"
          :disabled="otp.expired.value"
          @click="verify()"
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
          @click="resend"
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
