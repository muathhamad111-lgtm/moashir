<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import MfaChallengeForm from '@/components/auth/MfaChallengeForm.vue'
import MfaEnrollmentPanel from '@/components/auth/MfaEnrollmentPanel.vue'
import RecoveryCodesPanel from '@/components/auth/RecoveryCodesPanel.vue'
import { useAuthStore } from '@/stores/auth'
import { useAuthFlowStore } from '@/stores/authFlow'
import { useLoginOutcome } from '@/composables/useLoginOutcome'
import { logger } from '@/utils/logger'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { LOGIN_STEPS } from '@/constants/loginSteps'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const authFlow = useAuthFlowStore()
const { continueAfter } = useLoginOutcome()

const step = ref(authFlow.pendingStep)
const enrollment = ref(null)
const enrollmentStatus = ref('loading')
const submitting = ref(false)
const error = ref('')
const completedOutcome = ref(null)

const isEnrollment = computed(() => step.value === LOGIN_STEPS.MFA_ENROLLMENT)
const recoveryCodes = computed(() => completedOutcome.value?.recoveryCodes ?? [])
const titleKey = computed(() => (isEnrollment.value ? 'auth.mfa.enroll.title' : 'auth.mfa.challenge.title'))
const subtitleKey = computed(() =>
  isEnrollment.value ? 'auth.mfa.enroll.subtitle' : 'auth.mfa.challenge.subtitle',
)

function errorMessage(caught) {
  return (
    caught?.normalized?.fieldErrors?.code?.[0] ||
    caught?.normalized?.fieldErrors?.recoveryCode?.[0] ||
    caught?.normalized?.message ||
    t('auth.mfa.errors.invalid')
  )
}

async function loadEnrollment() {
  enrollmentStatus.value = 'loading'
  try {
    enrollment.value = await auth.startMfaEnrollment()
    enrollmentStatus.value = 'success'
  } catch {
    enrollmentStatus.value = 'error'
  }
}

async function attempt(action, payload, name) {
  if (submitting.value) return null
  submitting.value = true
  error.value = ''
  logger.breadcrumb('form', name, { fields: Object.keys(payload) })
  try {
    return await action(payload)
  } catch (caught) {
    error.value = errorMessage(caught)
    return null
  } finally {
    submitting.value = false
  }
}

async function submitChallenge(payload) {
  const outcome = await attempt(auth.challengeMfa, payload, 'auth.mfa.challenge')
  if (outcome) continueAfter(outcome)
}

async function confirmEnrollment(payload) {
  const outcome = await attempt(auth.confirmMfaEnrollment, payload, 'auth.mfa.confirm')
  if (!outcome) return
  if (outcome.recoveryCodes.length) {
    completedOutcome.value = outcome
    return
  }
  continueAfter(outcome)
}

function finishEnrollment() {
  continueAfter(completedOutcome.value)
}

onMounted(() => {
  if (step.value !== LOGIN_STEPS.MFA_CHALLENGE && step.value !== LOGIN_STEPS.MFA_ENROLLMENT) {
    router.replace({ name: ROUTE_NAMES.AUTH_LOGIN })
    return
  }
  if (isEnrollment.value) loadEnrollment()
})
</script>

<template>
  <section class="auth-form auth-mfa">
    <header class="auth-form__header">
      <h1 class="auth-form__title">{{ t(titleKey) }}</h1>
      <p class="auth-form__subtitle">{{ t(subtitleKey) }}</p>
    </header>

    <div class="auth-form__body">
      <RecoveryCodesPanel v-if="recoveryCodes.length" :codes="recoveryCodes" @continue="finishEnrollment" />

      <template v-else-if="isEnrollment">
        <Skeleton v-if="enrollmentStatus === 'loading'" variant="text" :count="4" />
        <ErrorState
          v-else-if="enrollmentStatus === 'error'"
          :message="t('auth.mfa.enroll.loadError')"
          @retry="loadEnrollment"
        />
        <MfaEnrollmentPanel
          v-else
          :enrollment="enrollment"
          :submitting="submitting"
          :error="error"
          @confirm="confirmEnrollment"
        />
      </template>

      <MfaChallengeForm v-else :submitting="submitting" :error="error" @submit="submitChallenge" />
    </div>

    <p class="auth-form__switch">
      <router-link class="auth-form__link" :to="{ name: ROUTE_NAMES.AUTH_LOGIN }">
        {{ t('auth.otp.backToLogin') }}
      </router-link>
    </p>
  </section>
</template>
