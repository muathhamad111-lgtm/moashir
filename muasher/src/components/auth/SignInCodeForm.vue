<script setup>
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AuthFailureAlert from '@/components/auth/AuthFailureAlert.vue'
import SignInIdentifierField from '@/components/auth/SignInIdentifierField.vue'
import { useSignInCodeRequest } from '@/composables/useSignInCodeRequest'

const props = defineProps({
  identifier: { type: String, default: '' },
})

const emit = defineEmits(['update:identifier'])
const { t } = useI18n()
const { form, failure, submitting, onSubmit } = useSignInCodeRequest(props.identifier)
const [typedIdentifier] = form.defineField('identifier')

watch(typedIdentifier, (value) => emit('update:identifier', value))
</script>

<template>
  <div class="auth-form__panel">
    <AuthFailureAlert v-if="failure" :failure="failure" group="codeRequest" />

    <form class="auth-form__body" novalidate @submit.prevent="onSubmit">
      <fieldset class="auth-form__group" :disabled="submitting">
        <SignInIdentifierField v-model="typedIdentifier" :error="form.errors.value.identifier" />
      </fieldset>

      <div class="auth-form__actions">
        <BaseButton type="submit" variant="primary" :loading="submitting">
          {{ t('auth.login.code.submit') }}
        </BaseButton>
      </div>
    </form>
  </div>
</template>
