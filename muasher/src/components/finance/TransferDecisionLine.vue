<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { PAYMENT_STATUSES } from '@/constants/payments'
import { formatRiyadhDateTime } from '@/utils/datetime'

const props = defineProps({
  transfer: { type: Object, required: true },
})

const { t, te, locale } = useI18n()

const isRejected = computed(() => props.transfer.status === PAYMENT_STATUSES.REJECTED)
const reviewer = computed(
  () => props.transfer.reviewedBy?.name || t('finance.transfers.decision.superAdmin'),
)
const line = computed(() =>
  t(`finance.transfers.decision.${isRejected.value ? 'rejectedBy' : 'approvedBy'}`, {
    name: reviewer.value,
    time: formatRiyadhDateTime(props.transfer.reviewedAt, locale.value),
  }),
)
const reason = computed(() => {
  const key = `finance.transfers.rejectionReasons.${props.transfer.rejectionReason}`
  return props.transfer.rejectionReason && te(key) ? t(key) : ''
})
</script>

<template>
  <div class="transfer-decision-line" :class="{ 'transfer-decision-line--rejected': isRejected }">
    <p class="transfer-decision-line__title">
      <AppIcon :name="isRejected ? 'x' : 'check-circle'" />
      {{ line }}
    </p>
    <p v-if="reason" class="transfer-decision-line__reason">
      {{ t('finance.transfers.decision.reason', { reason }) }}
    </p>
    <i18n-t
      v-if="transfer.reviewNote"
      keypath="finance.transfers.decision.note"
      scope="global"
      tag="p"
      class="transfer-decision-line__note"
    >
      <template #note>
        <bdi>{{ transfer.reviewNote }}</bdi>
      </template>
    </i18n-t>
  </div>
</template>
