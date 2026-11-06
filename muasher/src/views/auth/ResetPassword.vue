<script setup>
import { toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import FieldInput from '@/components/form/FieldInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import PasswordPolicyHints from '@/components/auth/PasswordPolicyHints.vue'
import { usePasswordReset } from '@/composables/usePasswordRecovery'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  token: { type: String, required: true },
})

const { t } = useI18n()
const { form, tokenInvalid } = usePasswordReset(toRef(props, 'token'))

useHead({ title: () => t('auth.reset.title') })
</script>

<template>
  <section class="auth-form">
    <header class="auth-form__header">
      <h1 class="auth-form__title">{{ t('auth.reset.title') }}</h1>
      <p class="auth-form__subtitle">{{ t('auth.reset.subtitle') }}</p>
    </header>

    <div v-if="tokenInvalid" class="auth-status auth-status--danger" role="alert">
      <AppIcon name="unlink" class="auth-status__icon" />
      <p class="auth-status__text">{{ t('auth.reset.invalidLink') }}</p>
      <router-link class="auth-form__link" :to="{ name: ROUTE_NAMES.AUTH_FORGOT_PASSWORD }">
        {{ t('auth.reset.requestAgain') }}
      </router-link>
    </div>

    <form v-else class="auth-form__body" novalidate @submit.prevent="form.onSubmit">
      <fieldset class="auth-form__group" :disabled="form.submitting.value">
        <FieldInput
          name="email"
          type="email"
          input-dir="ltr"
          autocomplete="email"
          :label="t('auth.reset.email')"
          required
        />
        <FieldInput
          name="password"
          type="password"
          autocomplete="new-password"
          :label="t('auth.reset.password')"
          required
        />
        <FieldInput
          name="passwordConfirmation"
          type="password"
          autocomplete="new-password"
          :label="t('auth.reset.passwordConfirmation')"
          required
        />
        <PasswordPolicyHints id="reset-password-policy" with-history />
      </fieldset>
      <div class="auth-form__actions">
        <BaseButton type="submit" variant="primary" :loading="form.submitting.value">
          {{ t('auth.reset.submit') }}
        </BaseButton>
      </div>
    </form>

    <p class="auth-form__switch">
      <router-link class="auth-form__link" :to="{ name: ROUTE_NAMES.AUTH_LOGIN }">
        {{ t('auth.recovery.backToLogin') }}
      </router-link>
    </p>
  </section>
</template>
