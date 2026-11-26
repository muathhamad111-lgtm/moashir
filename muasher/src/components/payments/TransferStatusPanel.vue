<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import TransferProgress from './TransferProgress.vue'
import TransferProofChip from './TransferProofChip.vue'
import MoneyAmount from './MoneyAmount.vue'
import { PAYMENT_STATUSES } from '@/constants/payments'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { newRequestRouteOf } from '@/utils/payments/myPaymentRow'
import { formatRiyadhDateTime } from '@/utils/datetime'
import { ibanLast4 } from '@/utils/payments/iban'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  payment: { type: Object, required: true },
})

const { t, te, locale } = useI18n()

const VIEWS = {
  [PAYMENT_STATUSES.UNDER_REVIEW]: { key: 'underReview', icon: 'refresh-dots', tone: 'info' },
  [PAYMENT_STATUSES.REJECTED]: { key: 'rejected', icon: 'x', tone: 'danger' },
  [PAYMENT_STATUSES.PAID]: { key: 'paid', icon: 'check-circle', tone: 'success' },
}

const view = computed(
  () => VIEWS[props.payment.status] ?? { key: 'closed', icon: 'lock', tone: 'muted' },
)
const transfer = computed(() => props.payment.bankTransfer)
const isRejected = computed(() => props.payment.status === PAYMENT_STATUSES.REJECTED)
const showsProgress = computed(() =>
  [PAYMENT_STATUSES.UNDER_REVIEW, PAYMENT_STATUSES.PAID].includes(props.payment.status),
)
const reasonLabel = computed(() => {
  const key = `portal.transfer.rejectionReasons.${transfer.value?.rejectionReason}`
  return transfer.value?.rejectionReason && te(key) ? t(key) : ''
})
const account = computed(() =>
  transfer.value
    ? t('portal.transfer.account.line', {
        bank: localizedText(transfer.value.bankAccount.bankName, locale.value),
        last4: ibanLast4(transfer.value.bankAccount.iban),
      })
    : '',
)
const newRequest = computed(() => newRequestRouteOf(props.payment))
</script>

<template>
  <section class="transfer-status" :class="`transfer-status--${view.tone}`">
    <header class="transfer-status__header">
      <span class="transfer-status__icon" aria-hidden="true"><AppIcon :name="view.icon" /></span>
      <div>
        <h2 class="transfer-status__title">{{ t(`portal.transfer.status.${view.key}.title`) }}</h2>
        <p class="transfer-status__message">
          {{ t(`portal.transfer.status.${view.key}.message`) }}
        </p>
      </div>
    </header>

    <TransferProgress v-if="showsProgress" :status="payment.status" />

    <div v-if="isRejected" class="transfer-status__rejection" role="note">
      <p v-if="reasonLabel" class="transfer-status__reason">
        {{ t('portal.transfer.rejection.reason', { reason: reasonLabel }) }}
      </p>
      <i18n-t
        v-if="transfer?.reviewNote"
        keypath="portal.transfer.rejection.note"
        scope="global"
        tag="p"
        class="transfer-status__note"
      >
        <template #note>
          <bdi>{{ transfer.reviewNote }}</bdi>
        </template>
      </i18n-t>
      <p class="transfer-status__by">
        {{
          t('portal.transfer.rejection.by', {
            time: formatRiyadhDateTime(transfer?.reviewedAt ?? payment.rejectedAt, locale),
          })
        }}
      </p>
    </div>
    <RouterLink v-if="isRejected && newRequest" class="btn btn--primary btn--lg" :to="newRequest">
      <AppIcon name="plus" class="btn__icon" />
      <span class="btn__label">{{ t('portal.transfer.newRequest') }}</span>
    </RouterLink>
    <RouterLink
      v-if="payment.status === PAYMENT_STATUSES.PAID"
      class="btn btn--primary"
      :to="{ name: ROUTE_NAMES.PAYMENTS_DETAIL, params: { id: payment.id } }"
    >
      <AppIcon name="doc" class="btn__icon" />
      <span class="btn__label">{{ t('portal.transfer.viewReceipt') }}</span>
    </RouterLink>

    <div v-if="transfer" class="transfer-status__proof">
      <h3 class="transfer-status__subtitle">{{ t('portal.transfer.proof.title') }}</h3>
      <TransferProofChip :payment="payment" />
    </div>

    <dl v-if="transfer && !isRejected" class="transfer-status__facts">
      <div class="transfer-status__fact">
        <dt>{{ t('portal.transfer.facts.account') }}</dt>
        <dd>{{ account }}</dd>
      </div>
      <div class="transfer-status__fact">
        <dt>{{ t('portal.transfer.facts.amount') }}</dt>
        <dd>
          <MoneyAmount :value="payment.pricing.total" :currency="payment.pricing.currency" strong />
        </dd>
      </div>
      <div class="transfer-status__fact">
        <dt>{{ t('portal.transfer.facts.submittedAt') }}</dt>
        <dd>{{ formatRiyadhDateTime(transfer.submittedAt, locale) }}</dd>
      </div>
    </dl>
  </section>
</template>
