<script setup>
import { computed, defineAsyncComponent, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AuthFailureAlert from '@/components/auth/AuthFailureAlert.vue'
import { useSignIn } from '@/composables/useSignIn'
import { useLoginModes } from '@/composables/useLoginModes'
import { identifierHintOf } from '@/utils/auth/signInForm'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { AUTH_NOTICES } from '@/constants/loginSteps'
import AppIcon from '@/components/ui/AppIcon.vue'

const DemoLogin =
  import.meta.env.DEV && import.meta.env.VITE_DEMO_LOGIN === 'true'
    ? defineAsyncComponent(() => import('@/components/auth/DemoLogin.vue'))
    : null

const { t, locale } = useI18n()
const route = useRoute()
const passwordWasReset = computed(() => route.query.notice === AUTH_NOTICES.PASSWORD_RESET)
const { identifiers, load } = useLoginModes()
const { form, failure, submitting, onSubmit } = useSignIn()
const [identifier] = form.defineField('identifier')
const [password] = form.defineField('password')
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

    <AuthFailureAlert v-if="failure" :failure="failure" group="signIn" />

    <form class="auth-form__body" novalidate @submit.prevent="onSubmit">
      <fieldset class="auth-form__group" :disabled="submitting">
        <BaseInput
          v-model="identifier"
          name="identifier"
          autocomplete="username"
          input-dir="ltr"
          :label="t('auth.login.fields.identifier')"
          :hint="identifierHint"
          :error="form.errors.value.identifier"
          required
        />
        <BaseInput
          v-model="password"
          name="password"
          type="password"
          autocomplete="current-password"
          :label="t('auth.login.fields.password')"
          :error="form.errors.value.password"
          required
        />
        <router-link
          class="auth-form__link auth-form__forgot"
          :to="{ name: ROUTE_NAMES.AUTH_FORGOT_PASSWORD }"
        >
          {{ t('auth.login.forgotPassword') }}
        </router-link>
      </fieldset>

      <div class="auth-form__actions">
        <BaseButton type="submit" variant="primary" :loading="submitting">
          {{ t('auth.login.submit') }}
        </BaseButton>
      </div>
    </form>

    <component :is="DemoLogin" v-if="DemoLogin" />

    <p class="auth-form__switch">
      {{ t('auth.login.noAccount') }}
      <router-link class="auth-form__link" :to="{ name: ROUTE_NAMES.AUTH_REGISTER }">
        {{ t('auth.register.title') }}
      </router-link>
    </p>
  </section>
</template>
