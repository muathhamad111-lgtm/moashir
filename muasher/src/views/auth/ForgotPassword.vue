<script setup>
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import FieldInput from '@/components/form/FieldInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useForgotPassword } from '@/composables/usePasswordRecovery'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const { form, sent } = useForgotPassword()

useHead({ title: () => t('auth.forgot.title') })
</script>

<template>
  <section class="auth-form">
    <header class="auth-form__header">
      <h1 class="auth-form__title">{{ t('auth.forgot.title') }}</h1>
      <p class="auth-form__subtitle">{{ t('auth.forgot.subtitle') }}</p>
    </header>

    <div v-if="sent" class="auth-status auth-status--success" role="status">
      <AppIcon name="envelope" class="auth-status__icon" />
      <p class="auth-status__text">{{ t('auth.forgot.sent') }}</p>
    </div>

    <form v-else class="auth-form__body" novalidate @submit.prevent="form.onSubmit">
      <fieldset class="auth-form__group" :disabled="form.submitting.value">
        <FieldInput
          name="email"
          type="email"
          input-dir="ltr"
          autocomplete="email"
          :label="t('auth.forgot.email')"
          :hint="t('auth.forgot.emailHint')"
          required
        />
      </fieldset>
      <div class="auth-form__actions">
        <BaseButton type="submit" variant="primary" :loading="form.submitting.value">
          {{ t('auth.forgot.submit') }}
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
