<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import PaymentStatusBadge from '@/components/payments/PaymentStatusBadge.vue'
import ProofViewer from './ProofViewer.vue'
import TransferRequestFacts from './TransferRequestFacts.vue'
import TransferFlags from './TransferFlags.vue'
import DuplicateProofNotice from './DuplicateProofNotice.vue'
import TransferDecisionLine from './TransferDecisionLine.vue'
import { useBankTransferReview } from '@/composables/useBankTransferReview'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'

// One bank transfer over the queue: the receipt beside the facts to match it against. The decision
// itself opens the page's approve or reject dialog (confirmation, or a required reason).
const props = defineProps({
  modelValue: { type: Boolean, required: true },
  paymentId: { type: String, required: true },
})

const emit = defineEmits(['update:modelValue', 'approve', 'reject'])
const { t, locale } = useI18n()
const review = useBankTransferReview(props.paymentId)
const { status, transfer } = review

const title = computed(() =>
  transfer.value
    ? `${t('finance.transfers.detail.heading', {
        entity: localizedText(transfer.value.entity.name, locale.value),
      })} \u2068${transfer.value.reference}\u2069`
    : t('finance.transfers.detail.title'),
)
const duplicates = computed(() => transfer.value?.duplicateProofs ?? [])
const fullPage = computed(() => ({
  name: ROUTE_NAMES.FINANCE_TRANSFER,
  params: { id: props.paymentId },
}))

onMounted(review.load)
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="xl"
    :title="title"
    :subtitle="t('finance.transferModal.subtitle')"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <Skeleton v-if="status === 'loading'" variant="block" />
    <ErrorState
      v-else-if="status === 'error'"
      :message="review.errorMessage.value"
      @retry="review.load()"
    />
    <EmptyState
      v-else-if="status === 'not-found'"
      icon="inbox"
      :title="t('finance.transfers.detail.notFound')"
    />
    <div v-else class="transfer-review-modal__layout">
      <ProofViewer
        v-if="transfer.proof"
        class="transfer-review-modal__proof"
        :proof="transfer.proof"
        :request-link="review.requestProofLink"
      />
      <div class="transfer-review-modal__side">
        <div class="transfer-review-modal__amount">
          <span class="transfer-review-modal__amount-label">{{
            t('finance.transfers.fields.amountDue')
          }}</span>
          <MoneyAmount
            class="transfer-review-modal__amount-value"
            :value="transfer.pricing?.total"
            :currency="transfer.pricing?.currency"
            strong
          />
          <span class="transfer-review-modal__hint">{{
            t('finance.transfers.detail.matchHint')
          }}</span>
          <PaymentStatusBadge :status="transfer.status" />
        </div>
        <DuplicateProofNotice v-if="duplicates.length" :duplicates="duplicates" />
        <dl class="transfer-facts">
          <div class="transfer-facts__row">
            <dt>{{ t('finance.transferModal.reference') }}</dt>
            <dd>
              <bdi dir="ltr">{{ transfer.reference }}</bdi>
            </dd>
          </div>
        </dl>
        <TransferRequestFacts :transfer="transfer" />
        <TransferFlags :transfer="transfer" />
        <TransferDecisionLine v-if="review.isDecided.value" :transfer="transfer" />
        <p v-if="review.isFrozen.value" class="transfer-review-modal__note" role="note">
          <AppIcon name="lock" />
          {{ t('finance.transfers.detail.frozen') }}
        </p>
        <p v-if="!review.isReviewer.value" class="transfer-review-modal__note" role="note">
          <AppIcon name="lock" />
          {{ t('finance.transfers.readOnly') }}
        </p>
      </div>
    </div>
    <template v-if="status === 'success'" #footer>
      <div class="finance-modal__footer">
        <BaseButton variant="ghost" icon="link" :to="fullPage">
          {{ t('finance.receiptModal.fullPage') }}
        </BaseButton>
        <div
          v-if="review.isReviewer.value && review.isUnderReview.value"
          class="transfer-review-modal__decisions"
        >
          <BaseButton
            variant="secondary"
            icon="x"
            :disabled="!review.canDecide.value"
            @click="emit('reject', transfer)"
          >
            {{ t('finance.transfers.reject.open') }}
          </BaseButton>
          <BaseButton
            variant="primary"
            icon="check"
            :disabled="!review.canDecide.value"
            @click="emit('approve', transfer)"
          >
            {{ t('finance.transfers.approve.open') }}
          </BaseButton>
        </div>
      </div>
    </template>
  </BaseModal>
</template>
