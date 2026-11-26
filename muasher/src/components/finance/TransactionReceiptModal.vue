<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppMenu from '@/components/ui/AppMenu.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import PaymentStatusBadge from '@/components/payments/PaymentStatusBadge.vue'
import PricingBreakdown from '@/components/payments/PricingBreakdown.vue'
import TransactionParties from './TransactionParties.vue'
import TransactionFacts from './TransactionFacts.vue'
import { useTransactionDetail } from '@/composables/useTransactionDetail'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { bankTransfersService } from '@/services/bankTransfersService'
import { PAYMENT_CHANNELS } from '@/constants/payments'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { formatRiyadhDateTime } from '@/utils/datetime'

// The receipt of one operation over the list. Staff actions (refund, settle, reissue) open the full
// page, where their dialogs live.
const props = defineProps({
  modelValue: { type: Boolean, required: true },
  paymentId: { type: String, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t, locale } = useI18n()
const router = useRouter()
const detail = useTransactionDetail(props.paymentId)
const { status, transaction, receipt } = detail
const downloads = useSignedDownload()

const fullPage = computed(() => ({
  name: ROUTE_NAMES.FINANCE_TRANSACTION,
  params: { id: props.paymentId },
}))
const title = computed(() =>
  receipt.value
    ? t('finance.receiptModal.title', { number: receipt.value.number })
    : t('finance.receiptModal.operationTitle', { reference: transaction.value?.reference ?? '' }),
)
const subtitle = computed(() =>
  receipt.value
    ? t('finance.receiptModal.issued', {
        reference: transaction.value.reference,
        date: formatRiyadhDateTime(receipt.value.issuedAt, locale.value),
      })
    : '',
)
const isTransfer = computed(() => transaction.value?.channel === PAYMENT_CHANNELS.BANK_TRANSFER)
const staffActions = computed(() =>
  [
    detail.canRecordRefund.value && {
      key: 'refund',
      label: t('finance.refund.open'),
      icon: 'undo',
    },
    detail.canSettle.value && {
      key: 'settle',
      label: t('finance.settle.open'),
      icon: 'user-shield',
    },
    detail.canReissue.value && {
      key: 'reissue',
      label: t('finance.receipt.reissue'),
      icon: 'refresh',
    },
  ].filter(Boolean),
)

function openAction(action) {
  router.push({ ...fullPage.value, query: { action } })
}

function print() {
  window.print()
}

onMounted(detail.load)
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="lg"
    :title="status === 'success' ? title : t('finance.receiptModal.loading')"
    :subtitle="status === 'success' ? subtitle : ''"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <Skeleton v-if="status === 'loading'" variant="text" :count="6" />
    <ErrorState
      v-else-if="status === 'error'"
      :message="detail.errorMessage.value"
      @retry="detail.load"
    />
    <EmptyState
      v-else-if="status === 'not-found'"
      icon="sar"
      :title="t('finance.transaction.notFound')"
    />
    <div v-else class="receipt-modal__body">
      <div class="receipt-modal__total">
        <span class="receipt-modal__total-label">{{ t('finance.pricing.total') }}</span>
        <MoneyAmount
          class="receipt-modal__total-value"
          :value="transaction.pricing.total"
          :currency="transaction.pricing.currency"
          strong
        />
        <PaymentStatusBadge :status="transaction.status" />
      </div>
      <p v-if="receipt?.vatRegistrationNumber" class="receipt-modal__issuer">
        {{ t('finance.receiptModal.vatNumber') }}
        <bdi dir="ltr">{{ receipt.vatRegistrationNumber }}</bdi>
      </p>
      <TransactionParties :transaction="transaction" />
      <PricingBreakdown :pricing="transaction.pricing" />
      <TransactionFacts :transaction="transaction" />
      <div v-if="isTransfer" class="receipt-modal__attachment">
        <span class="receipt-modal__attachment-name">
          <AppIcon name="doc" />
          {{ t('finance.receiptModal.transferProof') }}
        </span>
        <BaseButton
          variant="secondary"
          size="sm"
          icon="download"
          :loading="downloads.busy.proof === true"
          @click="
            downloads.download('proof', () => bankTransfersService.requestProofDownload(paymentId))
          "
        >
          {{ t('finance.transfers.proof.open') }}
        </BaseButton>
      </div>
      <p class="receipt-modal__note">
        <AppIcon name="shield-info" />
        {{ t('finance.transfers.proof.logged') }}
      </p>
    </div>
    <template v-if="status === 'success'" #footer>
      <div class="finance-modal__footer">
        <div class="receipt-modal__actions">
          <BaseButton
            v-if="receipt"
            variant="primary"
            icon="download"
            :loading="downloads.busy.receipt === true"
            :disabled="downloads.unavailable.receipt === true"
            @click="downloads.download('receipt', detail.requestReceiptLink)"
          >
            {{ t('finance.receiptModal.downloadPdf') }}
          </BaseButton>
          <BaseButton variant="secondary" icon="printer" @click="print">
            {{ t('finance.receiptModal.print') }}
          </BaseButton>
        </div>
        <div class="receipt-modal__actions">
          <BaseButton variant="ghost" icon="link" :to="fullPage">
            {{ t('finance.receiptModal.fullPage') }}
          </BaseButton>
          <AppMenu
            v-if="staffActions.length"
            :actions="staffActions"
            :label="t('finance.receiptModal.moreActions')"
            @select="openAction"
          />
        </div>
      </div>
    </template>
  </BaseModal>
</template>
