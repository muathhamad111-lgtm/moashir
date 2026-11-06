<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AuthFailureAlert from '@/components/auth/AuthFailureAlert.vue'
import SignInIdentifierField from '@/components/auth/SignInIdentifierField.vue'
import { useSignInCodeRequest } from '@/composables/useSignInCodeRequest'
import { OTP_CHANNELS } from '@/constants/signIn'

const props = defineProps({
  identifier: { type: String, default: '' },
  identifierHint: { type: String, default: '' },
  codeChannels: { type: Array, default: () => [OTP_CHANNELS.EMAIL] },
})

const emit = defineEmits(['update:identifier'])
const { t } = useI18n()
const { form, failure, submitting, onSubmit } = useSignInCodeRequest(props.identifier)
const [typedIdentifier] = form.defineField('identifier')

const smsAllowed = computed(() => props.codeChannels.includes(OTP_CHANNELS.SMS))
const channelHint = computed(() =>
  smsAllowed.value
    ? { icon: 'send', text: t('auth.login.code.channelSms') }
    : { icon: 'envelope', text: t('auth.login.code.channelEmail') },
)

watch(typedIdentifier, (value) => emit('update:identifier', value))
</script>

<template>
  <div class="auth-form__panel">
    <AuthFailureAlert v-if="failure" :failure="failure" group="codeRequest" />

    <form class="auth-form__body" novalidate @submit.prevent="onSubmit">
      <fieldset class="auth-form__group" :disabled="submitting">
        <SignInIdentifierField
          v-model="typedIdentifier"
          :hint="identifierHint"
          :error="form.errors.value.identifier"
        />
        <div class="sign-in-code__notes">
          <p class="sign-in-code__note" data-note="channel">
            <AppIcon :name="channelHint.icon" class="sign-in-code__icon" />
            <span>{{ channelHint.text }}</span>
          </p>
          <p class="sign-in-code__note" data-note="entity-only">
            <AppIcon name="shield-info" class="sign-in-code__icon" />
            <span>{{ t('auth.login.code.entityOnly') }}</span>
          </p>
        </div>
      </fieldset>

      <div class="auth-form__actions">
        <BaseButton type="submit" variant="primary" :loading="submitting">
          {{ t('auth.login.code.submit') }}
        </BaseButton>
      </div>
    </form>
  </div>
</template>
