<script setup>
import { toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldCheckbox from '@/components/form/FieldCheckbox.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import PasswordPolicyHints from '@/components/auth/PasswordPolicyHints.vue'
import InvitationTokenState from '@/components/auth/InvitationTokenState.vue'
import LegalConsentNote from '@/components/legal/LegalConsentNote.vue'
import { useInvitationAcceptance } from '@/composables/useInvitationAcceptance'
import { ROUTE_NAMES } from '@/constants/routeNames'

const props = defineProps({
  token: { type: String, required: true },
})

const { t } = useI18n()
const { form, status, terms, termsRequired, loadTerms } = useInvitationAcceptance(
  toRef(props, 'token'),
)

useHead({ title: () => t('auth.invitation.title') })
</script>

<template>
  <section class="auth-form">
    <header class="auth-form__header">
      <h1 class="auth-form__title">{{ t('auth.invitation.title') }}</h1>
      <p class="auth-form__subtitle">{{ t('auth.invitation.subtitle') }}</p>
    </header>

    <div v-if="status === 'loading'" class="auth-form__body" aria-busy="true">
      <Skeleton variant="text" :count="4" />
    </div>
    <ErrorState
      v-else-if="status === 'error'"
      :message="t('auth.invitation.loadFailed')"
      @retry="loadTerms"
    />
    <InvitationTokenState v-else-if="status !== 'ready'" :state="status" />

    <form v-else class="auth-form__body" novalidate @submit.prevent="form.onSubmit">
      <fieldset class="auth-form__group" :disabled="form.submitting.value">
        <FieldInput
          name="password"
          type="password"
          autocomplete="new-password"
          :label="t('auth.invitation.password')"
          required
        />
        <FieldInput
          name="passwordConfirmation"
          type="password"
          autocomplete="new-password"
          :label="t('auth.invitation.passwordConfirmation')"
          required
        />
        <PasswordPolicyHints id="invitation-password-policy" />
      </fieldset>
      <fieldset v-if="termsRequired" class="auth-form__group">
        <legend class="auth-form__legend">{{ t('auth.invitation.termsTitle') }}</legend>
        <FieldCheckbox
          name="termsAccepted"
          :label="t('auth.invitation.termsAccepted', { version: terms.terms.version })"
        />
        <LegalConsentNote :kinds="['terms', 'privacy']" />
      </fieldset>
      <div class="auth-form__actions">
        <BaseButton type="submit" variant="primary" :loading="form.submitting.value">
          {{ t('auth.invitation.submit') }}
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
