<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import OtpInput from '@/components/form/OtpInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const TOTP_LENGTH = 6

const props = defineProps({
  enrollment: { type: Object, required: true },
  submitting: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const emit = defineEmits(['confirm'])
const { t } = useI18n()

const code = ref('')
const localError = ref('')

const qrSource = computed(() =>
  props.enrollment.qrSvg
    ? `data:image/svg+xml;charset=utf-8,${encodeURIComponent(props.enrollment.qrSvg)}`
    : '',
)

const shownError = computed(() => localError.value || props.error)

function confirm() {
  localError.value = ''
  if (code.value.length !== TOTP_LENGTH) {
    localError.value = t('auth.mfa.errors.incomplete')
    return
  }
  emit('confirm', { code: code.value })
}
</script>

<template>
  <form class="auth-mfa__form" novalidate @submit.prevent="confirm">
    <figure class="auth-mfa__qr">
      <img
        v-if="qrSource"
        class="auth-mfa__qr-image"
        :src="qrSource"
        :alt="t('auth.mfa.enroll.qrAlt')"
      />
      <figcaption class="auth-mfa__qr-caption">{{ t('auth.mfa.enroll.qrHint') }}</figcaption>
    </figure>

    <div class="auth-mfa__manual">
      <span class="auth-mfa__manual-label">{{ t('auth.mfa.enroll.manualKeyLabel') }}</span>
      <code class="auth-mfa__manual-key" dir="ltr">{{ enrollment.manualEntryKey }}</code>
    </div>

    <OtpInput
      v-model="code"
      :length="TOTP_LENGTH"
      :label="t('auth.mfa.enroll.inputLabel')"
      :invalid="Boolean(shownError)"
      :disabled="submitting"
      @complete="confirm"
    />
    <p v-if="shownError" class="auth-otp__error" role="alert">{{ shownError }}</p>

    <div class="auth-form__actions">
      <BaseButton type="submit" variant="primary" :loading="submitting">
        {{ t('auth.mfa.enroll.submit') }}
      </BaseButton>
    </div>
  </form>
</template>
