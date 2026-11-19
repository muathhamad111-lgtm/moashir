<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseFileUpload from '@/components/form/BaseFileUpload.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useVatCertificate } from '@/composables/useVatCertificate'
import { fieldLabelKey } from '@/utils/entityProfile/sections'
import { VAT_CERTIFICATE_RULES } from '@/constants/entityProfile'

defineProps({
  field: { type: Object, required: true },
})

const { t } = useI18n()
const vat = useVatCertificate()
const files = computed(() =>
  vat.certificate.value
    ? [{ id: vat.certificate.value.id, name: vat.certificate.value.name, status: 'done' }]
    : [],
)
const hint = computed(() =>
  t('portal.profile.billing.certificate.hint', { max: VAT_CERTIFICATE_RULES.maxMb }),
)
</script>

<template>
  <div class="vat-certificate profile-field-input--wide" data-testid="vat-certificate-upload">
    <BaseFileUpload
      :files="files"
      :label="t(fieldLabelKey(field.name))"
      :name="field.name"
      :accept="VAT_CERTIFICATE_RULES.accept"
      :hint="hint"
      :error="vat.error.value"
      :disabled="vat.busy.value || !vat.canUpload.value"
      required
      @select="vat.upload($event[0])"
      @remove="vat.remove"
    />
    <p v-if="!vat.canUpload.value" class="vat-certificate__note" role="note">
      {{ t('portal.profile.billing.certificate.saveFirst') }}
    </p>
    <BaseButton
      v-if="vat.certificate.value"
      variant="secondary"
      size="sm"
      icon="download"
      class="vat-certificate__download"
      :loading="vat.downloading.value"
      @click="vat.download"
    >
      {{ t('portal.profile.billing.certificate.download') }}
    </BaseButton>
  </div>
</template>
