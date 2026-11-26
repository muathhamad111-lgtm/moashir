<script setup>
import { computed, toRef } from 'vue'
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
import NationalIdField from '@/components/identity/NationalIdField.vue'
import InvitationSteps from '@/components/auth/InvitationSteps.vue'
import InvitationNdaStep from '@/components/auth/InvitationNdaStep.vue'
import { useInvitationAcceptance } from '@/composables/useInvitationAcceptance'
import { ROUTE_NAMES } from '@/constants/routeNames'

const props = defineProps({
  token: { type: String, required: true },
})

const { t } = useI18n()
const { form, status, terms, termsRequired, presetNationalId, nda, onNdaStep, load } =
  useInvitationAcceptance(toRef(props, 'token'))

const isTokenState = computed(() => ['invalid', 'expired'].includes(status.value))

useHead({ title: () => t('auth.invitation.title') })
</script>

<template>
  <section class="auth-form">
    <header class="auth-form__header">
      <h1 class="auth-form__title">{{ t('auth.invitation.title') }}</h1>
      <p v-if="!isTokenState" class="auth-form__subtitle">{{ t('auth.invitation.subtitle') }}</p>
    </header>

    <div v-if="status === 'loading'" class="auth-form__body" aria-busy="true">
      <Skeleton variant="text" :count="4" />
    </div>
    <ErrorState
      v-else-if="status === 'error'"
      :message="t('auth.invitation.loadFailed')"
      @retry="load"
    />
    <InvitationTokenState v-else-if="isTokenState" :state="status" />

    <InvitationSteps v-if="nda && status === 'ready'" :current="onNdaStep ? 'nda' : 'password'" />
    <InvitationNdaStep
      v-if="nda && onNdaStep && status === 'ready'"
      :agreement="nda"
      :busy="form.submitting.value"
      @accept="form.onSubmit"
      @back="onNdaStep = false"
    />
    <form
      v-else-if="status === 'ready'"
      class="auth-form__body"
      novalidate
      @submit.prevent="form.onSubmit"
    >
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
      <fieldset class="auth-form__group" data-testid="invitation-national-id">
        <legend class="auth-form__legend">{{ t('nationalIdentity.invitation.title') }}</legend>
        <p v-if="presetNationalId" class="auth-form__hint">
          <i18n-t keypath="nationalIdentity.invitation.preset" scope="global" tag="span">
            <template #number>
              <bdi dir="ltr" data-testid="invitation-national-id-preset">{{
                presetNationalId
              }}</bdi>
            </template>
          </i18n-t>
        </p>
        <p v-else class="auth-form__hint">{{ t('nationalIdentity.invitation.optional') }}</p>
        <NationalIdField
          :label="
            t(
              presetNationalId
                ? 'nationalIdentity.invitation.different'
                : 'nationalIdentity.field.label',
            )
          "
          :required="false"
        />
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
          {{ t(nda ? 'nda.invitation.next' : 'auth.invitation.submit') }}
        </BaseButton>
      </div>
    </form>

    <p v-if="!isTokenState" class="auth-form__switch">
      <router-link class="auth-form__link" :to="{ name: ROUTE_NAMES.AUTH_LOGIN }">
        {{ t('auth.recovery.backToLogin') }}
      </router-link>
    </p>
  </section>
</template>
