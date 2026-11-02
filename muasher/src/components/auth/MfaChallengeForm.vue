<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import OtpInput from '@/components/form/OtpInput.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const TOTP_LENGTH = 6

defineProps({
  submitting: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const emit = defineEmits(['submit'])
const { t } = useI18n()

const useRecoveryCode = ref(false)
const code = ref('')
const recoveryCode = ref('')
const localError = ref('')

const shownError = computed(() => localError.value)

function toggleMode() {
  useRecoveryCode.value = !useRecoveryCode.value
  localError.value = ''
}

function submit() {
  localError.value = ''
  if (useRecoveryCode.value) {
    if (!recoveryCode.value.trim()) {
      localError.value = t('auth.mfa.errors.incomplete')
      return
    }
    emit('submit', { recoveryCode: recoveryCode.value.trim() })
    return
  }
  if (code.value.length !== TOTP_LENGTH) {
    localError.value = t('auth.mfa.errors.incomplete')
    return
  }
  emit('submit', { code: code.value })
}
</script>

<template>
  <form class="auth-mfa__form" novalidate @submit.prevent="submit">
    <BaseInput
      v-if="useRecoveryCode"
      v-model="recoveryCode"
      name="recoveryCode"
      :label="t('auth.mfa.challenge.recoveryLabel')"
      :error="shownError || error"
      required
    />
    <template v-else>
      <OtpInput
        v-model="code"
        :length="TOTP_LENGTH"
        :label="t('auth.mfa.challenge.inputLabel')"
        :invalid="Boolean(shownError || error)"
        :disabled="submitting"
        @complete="submit"
      />
      <p v-if="shownError || error" class="auth-otp__error" role="alert">{{ shownError || error }}</p>
    </template>

    <div class="auth-form__actions">
      <BaseButton type="submit" variant="primary" :loading="submitting">
        {{ t('auth.mfa.challenge.submit') }}
      </BaseButton>
    </div>

    <button type="button" class="auth-form__link auth-mfa__toggle" @click="toggleMode">
      {{ useRecoveryCode ? t('auth.mfa.challenge.useCode') : t('auth.mfa.challenge.useRecovery') }}
    </button>
  </form>
</template>
