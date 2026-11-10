<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@unhead/vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import PortalAreaHeader from '@/components/portal/PortalAreaHeader.vue'
import MobileActionBar from '@/components/portal/MobileActionBar.vue'
import TestModeNotice from '@/components/payments/TestModeNotice.vue'
import CheckoutSummary from '@/components/payments/CheckoutSummary.vue'
import CheckoutStateNotice from '@/components/payments/CheckoutStateNotice.vue'
import MoyasarFormHost from '@/components/payments/MoyasarFormHost.vue'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import PaymentNotice from '@/components/payments/PaymentNotice.vue'
import TransferStatusPanel from '@/components/payments/TransferStatusPanel.vue'
import ChangeMethodDialog from '@/components/payments/ChangeMethodDialog.vue'
import { COMPLETION_STATES, usePaymentCompletion } from '@/composables/usePaymentCompletion'
import { useCardOrderCancel } from '@/composables/useCardOrderCancel'
import { PAYMENT_CHANNELS } from '@/constants/payments'
import { useToast } from '@/composables/useToast'
import { CHECKOUT_PHASES } from '@/composables/useCheckout'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { formatDate } from '@/utils/datetime'
import { financeErrorMessage } from '@/utils/payments/financeErrors'
import { entryRouteOf, paymentYearOf } from '@/utils/payments/myPaymentRow'
import AppIcon from '@/components/ui/AppIcon.vue'

const i18n = useI18n()
const { t, locale } = i18n
const route = useRoute()
const router = useRouter()
const toast = useToast()
const formArea = ref(null)

const completion = usePaymentCompletion(
  computed(() => route.params.id),
  {
    onReplaced: (id) => router.replace({ name: ROUTE_NAMES.PAYMENTS_COMPLETE, params: { id } }),
  },
)
const { checkout, state, payment, order } = completion
const isTransfer = computed(() => state.value === COMPLETION_STATES.TRANSFER)
const changeMethod = useCardOrderCancel({
  onCancelled: (cancelled) => {
    const entry = entryRouteOf(cancelled)
    if (entry)
      router.push({ ...entry, query: { ...entry.query, method: PAYMENT_CHANNELS.BANK_TRANSFER } })
  },
})

useHead({ title: () => t('portal.payments.completion.title') })

const breadcrumbs = computed(() => [
  {
    key: 'journey',
    label: t('portal.breadcrumb.journey'),
    to: { name: ROUTE_NAMES.APP_DASHBOARD },
  },
  { key: 'payments', label: t('portal.nav.payments'), to: { name: ROUTE_NAMES.PAYMENTS_MINE } },
  { key: 'complete', label: t('portal.payments.completion.title') },
])
const service = computed(() => (order.value ? t(`finance.purposes.${order.value.purpose}`) : ''))
const eyebrow = computed(() =>
  order.value
    ? t('portal.payments.completion.eyebrow', {
        service: service.value,
        year: paymentYearOf(order.value),
      })
    : '',
)
const validUntil = computed(() => {
  const expiresAt = checkout.checkout.value?.expiresAt ?? order.value?.expiresAt
  return expiresAt
    ? formatDate(expiresAt, locale.value, {
        hour: 'numeric',
        minute: '2-digit',
        timeZone: 'Asia/Riyadh',
      })
    : ''
})
const subtitle = computed(() =>
  validUntil.value && !isTransfer.value
    ? t(`portal.payments.completion.subtitle.${order.value?.purpose ?? 'accredited'}`, {
        time: validUntil.value,
      })
    : '',
)
const showForm = computed(() => checkout.phase.value === CHECKOUT_PHASES.READY)
const showStarting = computed(() =>
  [CHECKOUT_PHASES.IDLE, CHECKOUT_PHASES.STARTING].includes(checkout.phase.value),
)
const closedKey = computed(() => order.value?.status ?? 'cancelled')

function onVerifyFailed(error) {
  toast.error(financeErrorMessage(i18n, error, 'finance.checkout.verifyFailed'))
}

function focusForm() {
  formArea.value?.scrollIntoView({ block: 'start' })
  formArea.value?.querySelector('input, button')?.focus()
}
</script>

<template>
  <section class="portal-area payment-complete">
    <PortalAreaHeader
      :breadcrumbs="breadcrumbs"
      :eyebrow="eyebrow"
      :title="t('portal.payments.completion.title')"
      :subtitle="subtitle"
    />

    <div class="portal-area__body portal-area__body--aside payment-complete__body">
      <section v-if="isTransfer" class="portal-surface payment-complete__main">
        <TestModeNotice v-if="order.isTest" compact />
        <TransferStatusPanel :payment="order" />
        <RouterLink class="payment-complete__back" :to="{ name: ROUTE_NAMES.PAYMENTS_MINE }">
          <AppIcon name="arrow-left" />
          {{ t('portal.payments.completion.back') }}
        </RouterLink>
      </section>
      <section
        v-else
        ref="formArea"
        class="portal-surface payment-complete__main"
        aria-labelledby="pay-title"
      >
        <div class="payment-complete__title-row">
          <h2 id="pay-title" class="portal-surface__title">
            {{ t('portal.payments.completion.cardTitle') }}
          </h2>
          <button
            v-if="completion.canSwitchToTransfer.value && showForm"
            type="button"
            class="checkout__change"
            @click="changeMethod.open.value = true"
          >
            <AppIcon name="refresh" />
            {{ t('portal.checkoutMethod.changeMethod') }}
          </button>
        </div>
        <p class="portal-surface__text">
          {{ t('portal.payments.completion.cardIntro', { service }) }}
        </p>
        <TestModeNotice v-if="checkout.isTest.value" />

        <Skeleton v-if="state === COMPLETION_STATES.LOADING" variant="text" :count="4" />
        <EmptyState
          v-else-if="state === COMPLETION_STATES.MISSING"
          icon="folder"
          :title="t('portal.payments.completion.missing.title')"
          :message="t('portal.payments.completion.missing.message')"
        />
        <ErrorState
          v-else-if="state === COMPLETION_STATES.ERROR"
          :message="completion.errorMessage.value"
          @retry="completion.load"
        />
        <PaymentNotice
          v-else-if="state === COMPLETION_STATES.CLOSED"
          tone="warning"
          icon="info-circle"
          :title="t(`portal.payments.completion.closed.${closedKey}.title`)"
          :message="t(`portal.payments.completion.closed.${closedKey}.message`)"
        >
          <template #actions>
            <RouterLink
              class="payment-notice__link"
              :to="{ name: ROUTE_NAMES.PAYMENTS_DETAIL, params: { id: order.id } }"
            >
              {{ t('finance.checkout.viewPayment') }}
            </RouterLink>
          </template>
        </PaymentNotice>
        <template v-else>
          <Skeleton v-if="showStarting" variant="text" :count="4" />
          <template v-else-if="showForm">
            <p v-if="checkout.existingOrder.value" class="payment-complete__existing" role="note">
              {{ t('portal.payments.completion.sameOrder') }}
            </p>
            <MoyasarFormHost
              :key="payment.id"
              :payment="payment"
              :checkout="checkout.checkout.value"
              @verified="checkout.applyVerified"
              @verify-failed="onVerifyFailed"
            />
          </template>
          <CheckoutStateNotice
            v-else
            :phase="checkout.phase.value"
            :payment="payment"
            :failure="checkout.failure.value"
            :busy="checkout.isStarting.value"
            @restart="completion.startCheckout"
          />
        </template>

        <p class="payment-complete__secure">
          <AppIcon name="shield-info" />
          {{ t('finance.checkout.cardNote') }}
        </p>
        <RouterLink class="payment-complete__back" :to="{ name: ROUTE_NAMES.PAYMENTS_MINE }">
          <AppIcon name="arrow-left" />
          {{ t('portal.payments.completion.back') }}
        </RouterLink>
      </section>

      <aside class="portal-area__aside">
        <CheckoutSummary
          v-if="order"
          :purpose="order.purpose"
          :payment="payment"
          :channel="payment?.channel ?? PAYMENT_CHANNELS.ELECTRONIC"
          :show-status="isTransfer"
          :countdown="showForm ? checkout.countdown : null"
        />
      </aside>
    </div>

    <ChangeMethodDialog
      v-if="payment && !isTransfer"
      v-model="changeMethod.open.value"
      :reference="payment.reference"
      :action="() => changeMethod.cancel(payment)"
    />

    <MobileActionBar v-if="showForm && payment" :label="t('portal.payments.completion.barLabel')">
      <div class="payment-complete__bar">
        <span class="payment-complete__bar-total">
          <span class="payment-complete__bar-label">{{ t('portal.payments.totalWithVat') }}</span>
          <MoneyAmount :value="payment.pricing.total" :currency="payment.pricing.currency" strong />
        </span>
        <BaseButton variant="primary" icon="lock" @click="focusForm">
          {{ t('portal.payments.completion.payNow') }}
        </BaseButton>
      </div>
    </MobileActionBar>
  </section>
</template>
