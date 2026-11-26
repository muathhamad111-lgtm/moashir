<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import OtpInput from '@/components/form/OtpInput.vue'
import { EMAIL_CHANGE_STEPS, useEmailChange } from '@/composables/useEmailChange'
import { EMAIL_CHANGE_STATUSES } from '@/constants/entityEnums'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  modelValue: { type: Boolean, required: true },
})

const emit = defineEmits(['update:modelValue', 'completed'])

const { t } = useI18n()
const { form, otp, step, target, code, codeError, verifying, result, verify, reset } =
  useEmailChange({
    onCompleted: (data) => emit('completed', data),
  })

const awaitingApproval = computed(
  () => result.value?.status === EMAIL_CHANGE_STATUSES.AWAITING_APPROVAL,
)

function close() {
  emit('update:modelValue', false)
  reset()
}
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    :title="t('entities.email.dialogTitle')"
    :close-on-backdrop="false"
    @update:model-value="close"
  >
    <form
      v-if="step === EMAIL_CHANGE_STEPS.EMAIL"
      class="email-change"
      novalidate
      @submit.prevent="form.onSubmit"
    >
      <p class="email-change__intro">{{ t('entities.email.intro') }}</p>
      <FieldInput
        name="newEmail"
        type="email"
        input-dir="ltr"
        autocomplete="email"
        :label="t('entities.email.newEmail')"
        required
      />
      <div class="email-change__actions">
        <BaseButton type="submit" variant="primary" :loading="form.submitting.value">{{
          t('entities.email.sendCode')
        }}</BaseButton>
        <BaseButton variant="secondary" :disabled="form.submitting.value" @click="close">{{
          t('actions.cancel')
        }}</BaseButton>
      </div>
    </form>

    <div v-else-if="step === EMAIL_CHANGE_STEPS.CODE" class="email-change">
      <p class="email-change__intro">{{ t('entities.email.codeIntro', { email: target }) }}</p>
      <OtpInput
        v-model="code"
        :length="otp.length.value"
        :label="t('auth.otp.inputLabel')"
        :invalid="Boolean(codeError)"
        :disabled="verifying || otp.expired.value"
        @complete="verify"
      />
      <p v-if="codeError" class="email-change__error" role="alert">{{ codeError }}</p>
      <p class="email-change__expiry" aria-live="polite">
        {{
          otp.expired.value
            ? t('auth.otp.expired')
            : t('auth.otp.expiresIn', { time: otp.expiryClock.value })
        }}
      </p>
      <div class="email-change__actions">
        <BaseButton
          variant="primary"
          :loading="verifying"
          :disabled="otp.expired.value"
          @click="verify()"
        >
          {{ t('auth.otp.verify') }}
        </BaseButton>
        <BaseButton variant="secondary" :disabled="verifying" @click="reset">{{
          t('entities.email.changeAddress')
        }}</BaseButton>
      </div>
    </div>

    <div v-else class="email-change email-change--done" role="status">
      <AppIcon
        :name="awaitingApproval ? 'refresh-dots' : 'check-circle'"
        class="email-change__icon"
      />
      <p class="email-change__outcome">
        {{
          awaitingApproval
            ? t('entities.email.awaitingApproval', { email: result.newEmail })
            : t('entities.email.applied')
        }}
      </p>
      <div class="email-change__actions">
        <BaseButton variant="primary" @click="close">{{ t('actions.close') }}</BaseButton>
      </div>
    </div>
  </BaseModal>
</template>
