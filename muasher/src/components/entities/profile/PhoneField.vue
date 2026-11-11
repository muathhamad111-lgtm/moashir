<script setup>
import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import ProfileFieldCell from './ProfileFieldCell.vue'
import PhoneCodeForm from './PhoneCodeForm.vue'
import { usePhoneVerification } from '@/composables/usePhoneVerification'
import { formatLocalPhone } from '@/composables/useProfileFieldDisplay'
import { fieldLabelKey } from '@/utils/entityProfile/sections'

const props = defineProps({
  field: { type: Object, required: true },
  profile: { type: Object, required: true },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['add', 'verified'])
const { t } = useI18n()
const verification = usePhoneVerification({ onVerified: () => emit('verified') })
const { PHASES, phase, busy, code, errorText } = verification
const otp = reactive(verification.otp)

const phone = computed(() => (props.profile.phone ? formatLocalPhone(props.profile.phone) : ''))
const verified = computed(() => props.profile.phoneVerified === true)
const verifying = computed(() => phase.value === PHASES.CODE)
</script>

<template>
  <ProfileFieldCell
    :label="t(fieldLabelKey(field.name))"
    :value="phone"
    :wide="verifying"
    ltr
    data-testid="profile-phone"
  >
    <span v-if="phone" class="profile-field__meta">
      <Badge :variant="verified ? 'success' : 'warning'">
        {{ t(verified ? 'entities.phone.verified' : 'entities.phone.notVerified') }}
      </Badge>
      <button
        v-if="canEdit && !verified && !verifying"
        type="button"
        class="profile-field__action"
        :disabled="busy"
        @click="verification.send"
      >
        {{ t('portal.profile.phone.verify') }}
      </button>
    </span>
    <button v-else-if="canEdit" type="button" class="profile-field__action" @click="emit('add')">
      {{ t('portal.profile.missing.add') }}
    </button>
    <p v-if="errorText && !verifying" class="phone-otp__error" role="alert">{{ errorText }}</p>
    <PhoneCodeForm
      v-if="verifying"
      v-model:code="code"
      :otp="otp"
      :phone="phone"
      :busy="busy"
      :error-text="errorText"
      @confirm="verification.confirm"
      @resend="verification.send"
      @cancel="verification.cancel"
    />
  </ProfileFieldCell>
</template>
