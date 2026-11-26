<script setup>
import { useFormValues } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import PasswordPolicyHints from '@/components/auth/PasswordPolicyHints.vue'
import TermsConsentFields from '@/components/entities/form/TermsConsentFields.vue'
import RegistrationEmailField from '@/components/entities/registration/RegistrationEmailField.vue'
import NationalIdField from '@/components/identity/NationalIdField.vue'
import RegistrationDocumentField from './RegistrationDocumentField.vue'

defineProps({
  nationalIdRequired: { type: Boolean, default: false },
})

const { t } = useI18n()
const values = useFormValues()
</script>

<template>
  <div class="entity-form__sections">
    <div class="entity-form__grid">
      <FieldInput
        name="authorizedPersonName"
        :label="t('entities.fields.authorizedPersonName')"
        autocomplete="name"
        required
      />
      <FieldInput
        name="phone"
        type="tel"
        input-dir="ltr"
        inputmode="tel"
        autocomplete="tel"
        :label="t('entities.fields.phone')"
      />
      <NationalIdField v-if="nationalIdRequired" class="entity-form__wide" />
      <RegistrationDocumentField
        name="authorizationLetter"
        :label="t('entities.documents.kinds.authorizationLetter')"
      />
      <RegistrationEmailField class="entity-form__wide" />
      <FieldInput
        name="password"
        type="password"
        autocomplete="new-password"
        :label="t('entities.fields.password')"
        required
      />
      <FieldInput
        name="passwordConfirmation"
        type="password"
        autocomplete="new-password"
        :label="t('entities.fields.passwordConfirmation')"
        required
      />
      <PasswordPolicyHints
        id="registration-password-policy"
        class="entity-form__wide"
        :password="values.password ?? ''"
        :confirmation="values.passwordConfirmation ?? ''"
      />
    </div>
    <TermsConsentFields />
  </div>
</template>
