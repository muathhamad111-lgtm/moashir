<script setup>
import { computed, defineAsyncComponent, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { object as yupObject, string as yupString } from 'yup'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useLoginOutcome } from '@/composables/useLoginOutcome'
import { useAuthStore } from '@/stores/auth'
import { authService } from '@/services/authService'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { LOGIN_MODES, OTP_CHANNELS } from '@/constants/entityEnums'
import { AUTH_FLOW_INTENTS } from '@/constants/loginSteps'

const DemoLogin =
  import.meta.env.DEV && import.meta.env.VITE_DEMO_LOGIN === 'true'
    ? defineAsyncComponent(() => import('@/components/auth/DemoLogin.vue'))
    : null

const { t } = useI18n()
const auth = useAuthStore()
const { continueAfter } = useLoginOutcome()

const settingsStatus = ref('loading')
const settings = ref(null)
const mode = ref(null)

const availableModes = computed(() => {
  if (!settings.value) return []
  const modes = []
  if (settings.value.emailPassword) modes.push(LOGIN_MODES.EMAIL_PASSWORD)
  if (settings.value.emailOtp) modes.push(LOGIN_MODES.EMAIL_OTP)
  if (settings.value.smsOtp) modes.push(LOGIN_MODES.SMS_OTP)
  return modes
})

const isPasswordMode = computed(() => mode.value === LOGIN_MODES.EMAIL_PASSWORD)

async function loadSettings() {
  settingsStatus.value = 'loading'
  try {
    const { data } = await authService.getLoginModes()
    settings.value = data
    mode.value = availableModes.value[0] ?? null
    settingsStatus.value = mode.value ? 'success' : 'empty'
  } catch {
    settingsStatus.value = 'error'
  }
}

onMounted(loadSettings)

function modeLabel(value) {
  return t(`auth.login.modes.${value}`)
}

const schema = computed(() =>
  yupObject({
    officialEmail: yupString().required(t('validation.required')).email(t('validation.email')),
    password: isPasswordMode.value
      ? yupString().required(t('validation.required'))
      : yupString().optional(),
  }),
)

function signIn(values) {
  if (isPasswordMode.value) {
    return auth.loginWithPassword({ officialEmail: values.officialEmail, password: values.password })
  }
  const channel = mode.value === LOGIN_MODES.SMS_OTP ? OTP_CHANNELS.SMS : OTP_CHANNELS.EMAIL
  return auth.requestLoginOtp({ officialEmail: values.officialEmail, channel })
}

const form = useValidatedForm({
  schema,
  initialValues: { officialEmail: '', password: '' },
  action: 'auth.login.submit',
  submit: async (values) => {
    const outcome = await signIn(values)
    await continueAfter(outcome, { officialEmail: values.officialEmail, intent: AUTH_FLOW_INTENTS.LOGIN })
  },
})

const [officialEmail] = form.defineField('officialEmail')
const [password] = form.defineField('password')

function selectMode(value) {
  mode.value = value
}
</script>

<template>
  <section class="auth-form">
    <header class="auth-form__header">
      <h1 class="auth-form__title">{{ t('auth.login.title') }}</h1>
      <p class="auth-form__subtitle">{{ t('auth.login.subtitle') }}</p>
    </header>

    <div v-if="settingsStatus === 'loading'" class="auth-form__body">
      <Skeleton variant="text" :count="4" />
    </div>

    <ErrorState
      v-else-if="settingsStatus === 'error'"
      :message="t('auth.login.settingsError')"
      @retry="loadSettings"
    />

    <p v-else-if="settingsStatus === 'empty'" class="auth-form__notice" role="alert">
      {{ t('auth.login.noModes') }}
    </p>

    <div v-else class="auth-form__body">
      <div
        v-if="availableModes.length > 1"
        class="auth-form__modes"
        role="tablist"
        :aria-label="t('auth.login.modesLabel')"
      >
        <button
          v-for="option in availableModes"
          :key="option"
          type="button"
          role="tab"
          class="auth-form__mode"
          :class="{ 'is-active': option === mode }"
          :aria-selected="option === mode"
          @click="selectMode(option)"
        >
          {{ modeLabel(option) }}
        </button>
      </div>

      <form novalidate @submit.prevent="form.onSubmit">
        <fieldset class="auth-form__group" :disabled="form.submitting.value">
          <BaseInput
            v-model="officialEmail"
            name="officialEmail"
            type="email"
            :label="t('auth.login.fields.officialEmail')"
            :error="form.errors.value.officialEmail"
            required
          />
          <BaseInput
            v-if="isPasswordMode"
            v-model="password"
            name="password"
            type="password"
            :label="t('auth.login.fields.password')"
            :error="form.errors.value.password"
            required
          />
          <p v-else class="auth-form__hint">
            {{
              mode === LOGIN_MODES.SMS_OTP
                ? t('auth.login.otpSmsHint')
                : t('auth.login.otpEmailHint')
            }}
          </p>
        </fieldset>

        <div class="auth-form__actions">
          <BaseButton type="submit" variant="primary" :loading="form.submitting.value">
            {{ isPasswordMode ? t('auth.login.submit') : t('auth.login.sendCode') }}
          </BaseButton>
        </div>
      </form>
    </div>

    <component :is="DemoLogin" v-if="DemoLogin" />

    <p class="auth-form__switch">
      {{ t('auth.login.noAccount') }}
      <router-link class="auth-form__link" :to="{ name: ROUTE_NAMES.AUTH_REGISTER }">
        {{ t('auth.register.title') }}
      </router-link>
    </p>
  </section>
</template>
