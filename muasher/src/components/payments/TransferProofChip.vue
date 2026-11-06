<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { paymentsService } from '@/services/paymentsService'
import { PAYMENT_STATUSES } from '@/constants/payments'
import { formatFileSize } from '@/utils/payments/amountFormat'
import { isoDate } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  payment: { type: Object, required: true },
})

const { t, locale } = useI18n()
const downloads = useSignedDownload()
const KEY = 'proof'

const BADGES = {
  [PAYMENT_STATUSES.UNDER_REVIEW]: { variant: 'info', key: 'underReview' },
  [PAYMENT_STATUSES.REJECTED]: { variant: 'danger', key: 'rejected' },
  [PAYMENT_STATUSES.PAID]: { variant: 'success', key: 'accepted' },
}

const transfer = computed(() => props.payment.bankTransfer)
const badge = computed(() => BADGES[props.payment.status] ?? null)
const sizeText = computed(() =>
  t('finance.common.sizeKb', {
    size: formatFileSize(transfer.value.proof?.sizeBytes, locale.value),
  }),
)
const bankName = computed(() => localizedText(transfer.value.bankAccount.bankName, locale.value))

function download() {
  return downloads.download(KEY, () =>
    paymentsService.requestTransferProofDownload(props.payment.id),
  )
}
</script>

<template>
  <div
    v-if="transfer?.proof"
    class="proof-chip"
    :class="{ 'is-refused': payment.status === PAYMENT_STATUSES.REJECTED }"
  >
    <span class="proof-chip__icon" aria-hidden="true"><AppIcon name="doc" /></span>
    <span class="proof-chip__meta">
      <bdi class="proof-chip__name" dir="ltr">{{ transfer.proof.name }}</bdi>
      <i18n-t
        keypath="portal.transfer.proof.sent"
        scope="global"
        tag="span"
        class="proof-chip__detail"
      >
        <template #size>{{ sizeText }}</template>
        <template #date>
          <bdi dir="ltr">{{ isoDate(transfer.submittedAt) }}</bdi>
        </template>
        <template #bank>{{ bankName }}</template>
      </i18n-t>
    </span>
    <Badge v-if="badge" :variant="badge.variant">{{
      t(`portal.transfer.proof.badges.${badge.key}`)
    }}</Badge>
    <BaseButton
      variant="ghost"
      size="sm"
      icon-only
      icon="download"
      :loading="downloads.busy[KEY] === true"
      :aria-label="t('portal.transfer.proof.download', { name: transfer.proof.name })"
      @click="download"
    />
  </div>
</template>
