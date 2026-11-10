<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import PaymentStatusBadge from '@/components/payments/PaymentStatusBadge.vue'
import PricingBreakdown from '@/components/payments/PricingBreakdown.vue'
import ReceiptCard from '@/components/payments/ReceiptCard.vue'
import TestModeNotice from '@/components/payments/TestModeNotice.vue'
import TransactionFacts from '@/components/finance/TransactionFacts.vue'
import LinkedRequestCard from '@/components/finance/LinkedRequestCard.vue'
import RefundRecord from '@/components/finance/RefundRecord.vue'
import RefundFormDialog from '@/components/finance/RefundFormDialog.vue'
import ManualSettleDialog from '@/components/finance/ManualSettleDialog.vue'
import { useTransactionDetail } from '@/composables/useTransactionDetail'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { useToast } from '@/composables/useToast'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { PAYMENT_PURPOSES } from '@/constants/payments'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  id: { type: String, required: true },
})

const { t } = useI18n()
const toast = useToast()
const detail = useTransactionDetail(props.id)
const {
  status,
  transaction,
  receipt,
  refund,
  errorMessage,
  canRecordRefund,
  canReissue,
  canSettle,
  load,
  reissue,
  replace,
} = detail
const downloads = useSignedDownload()
const refundOpen = ref(false)
const reissueOpen = ref(false)
const settleOpen = ref(false)

useHead({ title: () => t('finance.transaction.title') })

function onReissued() {
  toast.success(t('finance.receipt.reissuedToast'))
}

onMounted(load)
</script>

<template>
  <section class="finance-page transaction-detail">
    <RouterLink class="transaction-detail__back" :to="{ name: ROUTE_NAMES.FINANCE_TRANSACTIONS }">
      <AppIcon name="arrow-left" />
      {{ t('finance.transaction.back') }}
    </RouterLink>

    <div v-if="status === 'loading'" class="transaction-detail__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="6" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
    <EmptyState
      v-else-if="status === 'not-found'"
      icon="sar"
      :title="t('finance.transaction.notFound')"
    />

    <template v-else>
      <header class="transaction-detail__header">
        <div>
          <p class="transaction-detail__eyebrow">{{ t('finance.transaction.eyebrow') }}</p>
          <h1 class="transaction-detail__title">
            <bdi dir="ltr">{{ transaction.reference }}</bdi>
          </h1>
        </div>
        <div class="transaction-detail__badges">
          <PaymentStatusBadge :status="transaction.status" />
          <Badge v-if="transaction.latePaid" variant="danger">{{
            t('finance.transactions.latePaid')
          }}</Badge>
          <Badge v-if="transaction.manuallySettled" variant="info">{{
            t('finance.transactions.manuallySettled')
          }}</Badge>
        </div>
        <div class="transaction-detail__actions">
          <BaseButton
            v-if="canRecordRefund"
            variant="primary"
            icon="undo"
            @click="refundOpen = true"
          >
            {{ t('finance.refund.open') }}
          </BaseButton>
          <BaseButton
            v-if="canSettle"
            variant="secondary"
            icon="user-shield"
            @click="settleOpen = true"
          >
            {{ t('finance.settle.open') }}
          </BaseButton>
        </div>
      </header>

      <ul v-if="canRecordRefund || canSettle" class="transaction-detail__hints">
        <li v-if="canRecordRefund" class="submit-hint">
          <AppIcon name="landmark" />
          {{ t('finance.refund.lead') }}
        </li>
        <li v-if="canSettle" class="submit-hint">
          <AppIcon name="user-shield" />
          {{ t('finance.settle.lead') }}
        </li>
      </ul>

      <TestModeNotice v-if="transaction.isTest" compact audience="staff" />
      <p v-if="transaction.latePaid" class="transaction-detail__late" role="note">
        {{ t('finance.transaction.latePaidNote') }}
      </p>

      <div class="transaction-detail__layout">
        <Card :title="t('finance.transaction.collected')">
          <PricingBreakdown :pricing="transaction.pricing" />
          <TransactionFacts :transaction="transaction" />
        </Card>
        <div class="transaction-detail__side">
          <LinkedRequestCard
            v-if="transaction.purpose === PAYMENT_PURPOSES.ACCREDITED"
            :linked-request="transaction.linkedRequest"
            :payment-status="transaction.status"
            :entity-id="transaction.entity?.id ?? null"
          />
          <ReceiptCard
            v-if="receipt"
            :receipt="receipt"
            :downloading="downloads.busy.receipt === true"
            :unavailable="downloads.unavailable.receipt === true"
            @download="downloads.download('receipt', detail.requestReceiptLink)"
          >
            <template v-if="canReissue" #actions>
              <BaseButton variant="ghost" icon="refresh" @click="reissueOpen = true">{{
                t('finance.receipt.reissue')
              }}</BaseButton>
            </template>
          </ReceiptCard>
          <RefundRecord
            v-if="refund"
            :refund="refund"
            :downloading="downloads.busy.evidence === true"
            @download="downloads.download('evidence', detail.requestEvidenceLink)"
          />
        </div>
      </div>

      <RefundFormDialog
        v-if="refundOpen"
        v-model="refundOpen"
        :transaction="transaction"
        @recorded="load"
      />
      <ManualSettleDialog
        v-if="settleOpen"
        v-model="settleOpen"
        :transaction="transaction"
        @settled="replace"
      />
      <ReasonDialog
        v-model="reissueOpen"
        :title="t('finance.receipt.reissueTitle')"
        :subtitle="t('finance.receipt.reissueMessage')"
        :submit-label="t('finance.receipt.reissue')"
        :action="reissue"
        action-name="finance.reissueReceipt"
        @done="onReissued"
      />
    </template>
  </section>
</template>
