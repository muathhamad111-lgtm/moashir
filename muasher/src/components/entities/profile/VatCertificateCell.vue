<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import ProfileFieldCell from './ProfileFieldCell.vue'
import { useVatCertificate } from '@/composables/useVatCertificate'
import { fieldLabelKey } from '@/utils/entityProfile/sections'

defineProps({
  field: { type: Object, required: true },
  profile: { type: Object, required: true },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['add', 'verified', 'changed'])
const { t } = useI18n()
const vat = useVatCertificate()
</script>

<template>
  <ProfileFieldCell
    :label="t(fieldLabelKey(field.name))"
    :value="profile.vatCertificate?.name ?? ''"
    :missing="field.missing"
    :can-add="canEdit"
    ltr
    wide
    data-field="vatCertificate"
    @add="emit('add')"
  >
    <BaseButton
      v-if="profile.vatCertificate"
      variant="ghost"
      size="sm"
      icon="download"
      class="profile-field__action"
      :loading="vat.downloading.value"
      @click="vat.download"
    >
      {{ t('portal.profile.billing.certificate.download') }}
    </BaseButton>
  </ProfileFieldCell>
</template>
