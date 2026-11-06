<script setup>
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AuthFailureAlert from '@/components/auth/AuthFailureAlert.vue'
import SignInIdentifierField from '@/components/auth/SignInIdentifierField.vue'
import { useSignIn } from '@/composables/useSignIn'
import { ROUTE_NAMES } from '@/constants/routeNames'

const props = defineProps({
  identifier: { type: String, default: '' },
  identifierHint: { type: String, default: '' },
})

const emit = defineEmits(['update:identifier'])
const { t } = useI18n()
const { form, failure, submitting, onSubmit } = useSignIn(props.identifier)
const [typedIdentifier] = form.defineField('identifier')
const [password] = form.defineField('password')

watch(typedIdentifier, (value) => emit('update:identifier', value))
</script>

<template>
  <div class="auth-form__panel">
    <AuthFailureAlert v-if="failure" :failure="failure" group="signIn" />

    <form class="auth-form__body" novalidate @submit.prevent="onSubmit">
      <fieldset class="auth-form__group" :disabled="submitting">
        <SignInIdentifierField
          v-model="typedIdentifier"
          :hint="identifierHint"
          :error="form.errors.value.identifier"
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
  </div>
</template>
