<script setup>
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import PasswordPolicyHints from '@/components/auth/PasswordPolicyHints.vue'
import { useOwnPasswordChange } from '@/composables/useOwnPasswordChange'

const { t } = useI18n()
const { form, saved } = useOwnPasswordChange()
</script>

<template>
  <Card :title="t('account.password.title')" class="change-password-card">
    <p class="account-page__muted">{{ t('account.password.subtitle') }}</p>

    <form
      class="change-password-card__form"
      novalidate
      data-testid="change-password-form"
      @submit.prevent="form.onSubmit"
    >
      <fieldset class="change-password-card__fields" :disabled="form.submitting.value">
        <FieldInput
          name="currentPassword"
          type="password"
          autocomplete="current-password"
          :label="t('account.password.current')"
          required
        />
        <FieldInput
          name="password"
          type="password"
          autocomplete="new-password"
          :label="t('account.password.new')"
          required
        />
        <FieldInput
          name="passwordConfirmation"
          type="password"
          autocomplete="new-password"
          :label="t('account.password.confirmation')"
          required
        />
        <PasswordPolicyHints
          id="change-password-policy"
          with-history
          :password="form.values.password"
          :confirmation="form.values.passwordConfirmation"
        />
      </fieldset>

      <div
        v-if="form.formError.value"
        class="auth-status auth-status--danger"
        role="alert"
        data-testid="change-password-error"
      >
        <AppIcon name="warning-triangle" class="auth-status__icon" />
        <p class="auth-status__text">{{ form.formError.value }}</p>
      </div>
      <div
        v-else-if="saved"
        class="auth-status auth-status--success"
        role="status"
        data-testid="change-password-saved"
      >
        <AppIcon name="check-circle" class="auth-status__icon" />
        <p class="auth-status__text">{{ t('account.password.saved') }}</p>
      </div>

      <div class="change-password-card__actions">
        <BaseButton type="submit" variant="primary" :loading="form.submitting.value">
          {{ t('account.password.submit') }}
        </BaseButton>
      </div>
    </form>
  </Card>
</template>
