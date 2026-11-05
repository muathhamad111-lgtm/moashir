<script setup>
import { computed, defineAsyncComponent, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import SignInMethodTabs from '@/components/auth/SignInMethodTabs.vue'
import SignInPasswordForm from '@/components/auth/SignInPasswordForm.vue'
import SignInCodeForm from '@/components/auth/SignInCodeForm.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useLoginModes } from '@/composables/useLoginModes'
import { useAuthFlowStore } from '@/stores/authFlow'
import { identifierHintOf } from '@/utils/auth/signInForm'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { AUTH_NOTICES } from '@/constants/loginSteps'
import { SIGN_IN_METHODS, SIGN_IN_METHOD_QUERY, SIGN_IN_METHOD_VALUES } from '@/constants/signIn'

const DemoLogin =
  import.meta.env.DEV && import.meta.env.VITE_DEMO_LOGIN === 'true'
    ? defineAsyncComponent(() => import('@/components/auth/DemoLogin.vue'))
    : null

const { t, locale } = useI18n()
const route = useRoute()
const authFlow = useAuthFlowStore()
const { identifiers, codeChannels, load } = useLoginModes()

const requestedMethod = route.query[SIGN_IN_METHOD_QUERY]
const method = ref(
  SIGN_IN_METHOD_VALUES.includes(requestedMethod) ? requestedMethod : SIGN_IN_METHODS.PASSWORD,
)
const identifier = ref(authFlow.identifier)
const passwordWasReset = computed(() => route.query.notice === AUTH_NOTICES.PASSWORD_RESET)
const identifierHint = computed(() => identifierHintOf(t, locale.value, identifiers.value))

onMounted(load)
</script>

<template>
  <section class="auth-form">
    <header class="auth-form__header">
      <h1 class="auth-form__title">{{ t('auth.login.title') }}</h1>
      <p class="auth-form__subtitle">{{ t('auth.login.subtitle') }}</p>
    </header>

    <div v-if="passwordWasReset" class="auth-status auth-status--success" role="status">
      <AppIcon name="check-circle" class="auth-status__icon" />
      <p class="auth-status__text">{{ t('auth.reset.done') }}</p>
    </div>

    <SignInMethodTabs v-model="method">
      <SignInPasswordForm
        v-if="method === SIGN_IN_METHODS.PASSWORD"
        v-model:identifier="identifier"
        :identifier-hint="identifierHint"
      />
      <SignInCodeForm
        v-else
        v-model:identifier="identifier"
        :identifier-hint="identifierHint"
        :code-channels="codeChannels"
      />
    </SignInMethodTabs>

    <component :is="DemoLogin" v-if="DemoLogin" />

    <p class="auth-form__switch">
      {{ t('auth.login.noAccount') }}
      <router-link class="auth-form__link" :to="{ name: ROUTE_NAMES.AUTH_REGISTER }">
        {{ t('auth.register.title') }}
      </router-link>
    </p>
  </section>
</template>
