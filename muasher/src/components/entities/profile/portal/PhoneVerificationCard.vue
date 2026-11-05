<script setup>
import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import PhoneCodeForm from './PhoneCodeForm.vue'
import { usePhoneVerification } from '@/composables/usePhoneVerification'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  profile: { type: Object, required: true },
  phoneDirty: { type: Boolean, default: false },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['verified'])
const { t } = useI18n()
const verification = usePhoneVerification({ onVerified: () => emit('verified') })
const { PHASES, phase, busy, code, errorText } = verification
const otp = reactive(verification.otp)

const phone = computed(() => props.profile.phone ?? '')
const verified = computed(() => props.profile.phoneVerified === true)
const canSend = computed(() => props.canEdit && Boolean(phone.value) && !verified.value)
</script>

<template>
  <Card :title="t('entities.phone.title')" class="phone-verification">
    <div class="phone-verification__body" data-testid="phone-verification">
      <p v-if="phone" class="phone-verification__current">
        <span class="phone-verification__label">{{ t('entities.fields.phone') }}</span>
        <bdi class="phone-verification__number" dir="ltr">{{ phone }}</bdi>
        <Badge :variant="verified ? 'success' : 'warning'">
          {{ t(verified ? 'entities.phone.verified' : 'entities.phone.notVerified') }}
        </Badge>
      </p>
      <p class="phone-verification__hint">
        <AppIcon name="info-circle" />
        <span>{{ t(verified ? 'entities.phone.verifiedHint' : 'entities.phone.why') }}</span>
      </p>
      <p v-if="!phone" class="phone-verification__note">{{ t('entities.phone.missing') }}</p>
      <p v-else-if="phoneDirty && !verified" class="phone-verification__note" role="status">
        {{ t('entities.phone.saveFirst') }}
      </p>
      <p v-if="errorText" class="phone-verification__error" role="alert">{{ errorText }}</p>
      <PhoneCodeForm
        v-if="phase === PHASES.CODE"
        v-model:code="code"
        :otp="otp"
        :busy="busy"
        @confirm="verification.confirm"
        @resend="verification.send"
        @cancel="verification.cancel"
      />
      <BaseButton
        v-else-if="canSend"
        variant="secondary"
        size="sm"
        icon="send"
        :loading="busy"
        :disabled="phoneDirty"
        @click="verification.send"
      >
        {{ t('entities.phone.send') }}
      </BaseButton>
    </div>
  </Card>
</template>
