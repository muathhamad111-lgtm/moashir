<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import PaymentStatusBadge from '@/components/payments/PaymentStatusBadge.vue'
import ProofViewer from '@/components/finance/ProofViewer.vue'
import TransferRequestFacts from '@/components/finance/TransferRequestFacts.vue'
import TransferDecisionLine from '@/components/finance/TransferDecisionLine.vue'
import DuplicateProofNotice from '@/components/finance/DuplicateProofNotice.vue'
import ApproveTransferDialog from '@/components/finance/ApproveTransferDialog.vue'
import RejectTransferDialog from '@/components/finance/RejectTransferDialog.vue'
import { useBankTransferReview } from '@/composables/useBankTransferReview'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  id: { type: String, required: true },
})

const { t, locale } = useI18n()
const review = useBankTransferReview(props.id)
const { status, transfer, errorMessage, decisions } = review

useHead({ title: () => t('finance.transfers.detail.title') })

const heading = computed(() =>
  transfer.value
    ? t('finance.transfers.detail.heading', {
        entity: localizedText(transfer.value.entity.name, locale.value),
      })
    : '',
)
const duplicates = computed(() => transfer.value?.duplicateProofs ?? [])

onMounted(review.load)
</script>

<template>
  <section class="finance-page transfer-review">
    <RouterLink class="transfer-review__back" :to="{ name: ROUTE_NAMES.FINANCE_TRANSFERS }">
      <AppIcon name="arrow-left" />
      {{ t('finance.transfers.detail.back') }}
    </RouterLink>

    <div v-if="status === 'loading'" class="transfer-review__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="6" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="review.load" />
    <EmptyState
      v-else-if="status === 'not-found'"
      icon="inbox"
      :title="t('finance.transfers.detail.notFound')"
    />

    <template v-else>
      <header class="transfer-review__header">
        <p class="transfer-review__eyebrow">{{ t('finance.transfers.detail.eyebrow') }}</p>
        <h1 class="transfer-review__title">
          {{ heading }}
          <bdi dir="ltr">{{ transfer.reference }}</bdi>
        </h1>
        <PaymentStatusBadge :status="transfer.status" />
      </header>

      <p v-if="decisions.conflict.value" class="transfer-review__conflict" role="alert">
        <AppIcon name="warning-triangle" />
        {{ t(`finance.transfers.conflict.${decisions.conflict.value.replace('.', '_')}`) }}
      </p>

      <div class="transfer-review__layout">
        <Card class="transfer-review__proof">
          <ProofViewer
            v-if="transfer.proof"
            :proof="transfer.proof"
            :request-link="review.requestProofLink"
          />
        </Card>

        <aside class="transfer-review__side">
          <p v-if="!review.isReviewer.value" class="transfer-review__read-only" role="note">
            <AppIcon name="lock" />
            {{ t('finance.transfers.readOnly') }}
          </p>

          <Card class="transfer-review__amount">
            <h2 class="transfer-review__amount-title">
              {{ t('finance.transfers.fields.amountDue') }}
            </h2>
            <MoneyAmount
              class="transfer-review__amount-value"
              :value="transfer.pricing?.total"
              :currency="transfer.pricing?.currency"
              strong
            />
            <p class="transfer-review__amount-hint">
              {{ t('finance.transfers.detail.matchHint') }}
            </p>
          </Card>

          <DuplicateProofNotice v-if="duplicates.length" :duplicates="duplicates" />

          <Card :title="t('finance.transfers.detail.requestTitle')">
            <TransferRequestFacts :transfer="transfer" />
          </Card>

          <TransferDecisionLine v-if="review.isDecided.value" :transfer="transfer" />

          <p v-if="review.isFrozen.value" class="transfer-review__frozen" role="note">
            <AppIcon name="lock" />
            {{ t('finance.transfers.detail.frozen') }}
          </p>

          <Card v-if="review.isReviewer.value && review.isUnderReview.value">
            <div class="transfer-review__decisions">
              <BaseButton
                variant="secondary"
                icon="x"
                :disabled="!review.canDecide.value"
                @click="decisions.openReject([transfer])"
              >
                {{ t('finance.transfers.reject.open') }}
              </BaseButton>
              <BaseButton
                variant="primary"
                icon="check"
                :disabled="!review.canDecide.value"
                @click="decisions.openApprove([transfer])"
              >
                {{ t('finance.transfers.approve.open') }}
              </BaseButton>
            </div>
          </Card>
        </aside>
      </div>

      <ApproveTransferDialog
        v-model="decisions.approveOpen.value"
        :targets="decisions.approveTargets.value"
        :asks-reason="decisions.asksReason.value"
        :action="decisions.approve"
      />
      <RejectTransferDialog
        v-model="decisions.rejectOpen.value"
        :targets="decisions.rejectTargets.value"
        :asks-reason="decisions.asksReason.value"
        :action="decisions.reject"
      />
    </template>
  </section>
</template>
