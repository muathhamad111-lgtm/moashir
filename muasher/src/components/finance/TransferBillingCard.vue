<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import EntityDetailsList from '@/components/entities/EntityDetailsList.vue'
import { useBillingRows } from '@/composables/useBillingRows'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { bankTransfersService } from '@/services/bankTransfersService'

const props = defineProps({
  paymentId: { type: String, required: true },
  billing: { type: Object, default: null },
  complete: { type: Boolean, default: false },
})

const DOWNLOAD_KEY = 'vat-certificate'
const { t } = useI18n()
const { rowsOf } = useBillingRows()
const downloads = useSignedDownload()
const rows = computed(() => rowsOf(props.billing))
const certificate = computed(() => props.billing?.vatCertificate ?? null)

function download() {
  return downloads.download(DOWNLOAD_KEY, () =>
    bankTransfersService.requestVatCertificateDownload(props.paymentId),
  )
}
</script>

<template>
  <Card class="transfer-billing" data-testid="transfer-billing">
    <template #header>
      <span class="transfer-billing__title">{{ t('finance.transfers.billing.title') }}</span>
      <Badge v-if="!complete" variant="warning" data-testid="transfer-billing-incomplete">
        <AppIcon name="warning-triangle" />
        {{ t('finance.transfers.billing.incomplete') }}
      </Badge>
    </template>
    <p v-if="!complete" class="transfer-billing__hint" role="note">
      {{ t('finance.transfers.billing.incompleteHint') }}
    </p>
    <EntityDetailsList :rows="rows" />
    <BaseButton
      v-if="certificate"
      variant="secondary"
      size="sm"
      icon="download"
      class="transfer-billing__download"
      :loading="Boolean(downloads.busy[DOWNLOAD_KEY])"
      @click="download"
    >
      {{ t('finance.transfers.billing.downloadCertificate') }}
    </BaseButton>
  </Card>
</template>
