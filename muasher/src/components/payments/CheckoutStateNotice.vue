<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import PaymentNotice from './PaymentNotice.vue'
import { CHECKOUT_PHASES } from '@/composables/useCheckout'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { financeErrorMessage } from '@/utils/payments/financeErrors'
import { ACCREDITED_CODES } from '@/constants/accredited'
import { SELF_ASSESSMENT_CODES } from '@/constants/assessment'
import { PATH_CODES } from '@/constants/entityPaths'

const RETRYABLE_CODES = ['payment.checkout_in_progress', 'provider_unavailable', null]

const props = defineProps({
  phase: { type: String, required: true },
  payment: { type: Object, default: null },
  failure: { type: Object, default: null },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['restart'])
const i18n = useI18n()
const { t } = i18n

const blockedMessage = computed(() =>
  financeErrorMessage(i18n, props.failure?.error, 'finance.checkout.startFailed'),
)
const canRetry = computed(() => RETRYABLE_CODES.includes(props.failure?.code ?? null))
const PATH_PAGES = {
  self: ROUTE_NAMES.PATHS_SELF,
  accredited: ROUTE_NAMES.PATHS_ACCREDITED,
  award: ROUTE_NAMES.PATHS_AWARD,
}

const BLOCKED_LINKS = {
  [ACCREDITED_CODES.REQUEST_OPEN]: (meta) =>
    meta?.assessmentId
      ? {
          to: { name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_FORM, params: { id: meta.assessmentId } },
          labelKey: 'accredited.current.openRequest',
        }
      : null,
  [SELF_ASSESSMENT_CODES.DRAFT_EXISTS]: () => ({
    to: { name: ROUTE_NAMES.ASSESSMENT_SELF },
    labelKey: 'paths.actions.continueSelf',
  }),
  [PATH_CODES.PAYMENT_NOT_REQUIRED]: (meta) =>
    PATH_PAGES[meta?.purpose]
      ? { to: { name: PATH_PAGES[meta.purpose] }, labelKey: 'finance.checkout.backToPath' }
      : null,
}

const blockedLink = computed(
  () => BLOCKED_LINKS[props.failure?.code]?.(props.failure.error?.normalized?.meta ?? null) ?? null,
)
</script>

<template>
  <PaymentNotice
    v-if="phase === CHECKOUT_PHASES.IN_PROGRESS"
    tone="warning"
    icon="refresh-dots"
    :title="t('finance.checkout.inProgressTitle')"
    :message="t('finance.checkout.inProgressMessage')"
  >
    <template #actions>
      <RouterLink
        class="payment-notice__link"
        :to="{ name: ROUTE_NAMES.PAYMENTS_DETAIL, params: { id: payment.id } }"
      >
        {{ t('finance.checkout.viewPayment') }}
      </RouterLink>
    </template>
  </PaymentNotice>

  <PaymentNotice
    v-else-if="phase === CHECKOUT_PHASES.EXPIRED"
    tone="warning"
    icon="clock"
    :title="t('finance.checkout.expiredTitle')"
    :message="t('finance.checkout.expiredMessage')"
    live
  >
    <template #actions>
      <BaseButton variant="primary" icon="refresh" :loading="busy" @click="emit('restart')">
        {{ t('finance.checkout.startAgain') }}
      </BaseButton>
    </template>
  </PaymentNotice>

  <PaymentNotice
    v-else-if="phase === CHECKOUT_PHASES.BLOCKED"
    tone="danger"
    icon="warning-triangle"
    :title="t('finance.checkout.blockedTitle')"
    :message="blockedMessage"
    live
  >
    <template v-if="canRetry || blockedLink" #actions>
      <BaseButton
        v-if="canRetry"
        variant="primary"
        icon="refresh"
        :loading="busy"
        @click="emit('restart')"
      >
        {{ t('finance.checkout.retry') }}
      </BaseButton>
      <RouterLink v-if="blockedLink" class="payment-notice__link" :to="blockedLink.to">
        {{ t(blockedLink.labelKey) }}
      </RouterLink>
    </template>
  </PaymentNotice>
</template>
