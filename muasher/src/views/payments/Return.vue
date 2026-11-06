<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import { useRoute } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import PaymentOutcome from '@/components/payments/PaymentOutcome.vue'
import PricingBreakdown from '@/components/payments/PricingBreakdown.vue'
import TestModeNotice from '@/components/payments/TestModeNotice.vue'
import { RETURN_STATES, usePaymentReturn } from '@/composables/usePaymentReturn'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { financeErrorMessage } from '@/utils/payments/financeErrors'
import { providerPaymentIdOf } from '@/utils/payments/paymentReturn'

const props = defineProps({
  id: { type: String, required: true },
})

const i18n = useI18n()
const { t } = i18n
const route = useRoute()
const { state, payment, failure, start } = usePaymentReturn(
  props.id,
  providerPaymentIdOf(route.query),
)

useHead({ title: () => t('finance.return.pageTitle') })

const CHECKOUT_ROUTES = {
  accredited: ROUTE_NAMES.ASSESSMENT_ACCREDITED_PAY,
  bag: ROUTE_NAMES.BAG_PAY,
}
const NEXT_ROUTES = { accredited: ROUTE_NAMES.ASSESSMENT_ACCREDITED, bag: ROUTE_NAMES.BAG_INDEX }

const errorMessage = computed(() =>
  failure.value ? financeErrorMessage(i18n, failure.value, 'finance.return.error.message') : '',
)
const checkoutRoute = computed(() => CHECKOUT_ROUTES[payment.value?.purpose])
const nextRoute = computed(() => NEXT_ROUTES[payment.value?.purpose])
const canRetry = computed(
  () =>
    [RETURN_STATES.FAILED, RETURN_STATES.CANCELLED].includes(state.value) && checkoutRoute.value,
)
const canVerifyAgain = computed(() =>
  [RETURN_STATES.NOTIFY, RETURN_STATES.ERROR].includes(state.value),
)

onMounted(start)
</script>

<template>
  <section class="payment-return">
    <header class="payment-return__header">
      <p class="payment-return__eyebrow">{{ t('finance.return.eyebrow') }}</p>
      <h1 class="payment-return__title">{{ t('finance.return.pageTitle') }}</h1>
    </header>

    <TestModeNotice v-if="payment?.isTest" compact />

    <Card>
      <PaymentOutcome :state="state" :payment="payment" :error-message="errorMessage">
        <dl v-if="payment" class="payment-return__facts">
          <div class="payment-return__fact">
            <dt>{{ t('finance.fields.reference') }}</dt>
            <dd>
              <bdi dir="ltr">{{ payment.reference }}</bdi>
            </dd>
          </div>
          <div class="payment-return__fact">
            <dt>{{ t('finance.fields.service') }}</dt>
            <dd>{{ t(`finance.purposes.${payment.purpose}`) }}</dd>
          </div>
        </dl>
        <PricingBreakdown v-if="payment" :pricing="payment.pricing" />
        <template #actions>
          <RouterLink
            v-if="state === RETURN_STATES.PAID && payment?.receipt"
            class="payment-return__link payment-return__link--primary"
            :to="{ name: ROUTE_NAMES.PAYMENTS_DETAIL, params: { id } }"
          >
            {{ t('finance.return.viewReceipt') }}
          </RouterLink>
          <RouterLink
            v-if="state === RETURN_STATES.PAID && nextRoute"
            class="payment-return__link"
            :to="{ name: nextRoute }"
          >
            {{ t(`finance.return.next.${payment.purpose}`) }}
          </RouterLink>
          <RouterLink
            v-if="canRetry"
            class="payment-return__link payment-return__link--primary"
            :to="{ name: checkoutRoute }"
          >
            {{ t('finance.return.tryAgain') }}
          </RouterLink>
          <BaseButton v-if="canVerifyAgain" variant="secondary" icon="refresh" @click="start">
            {{ t('finance.return.checkAgain') }}
          </BaseButton>
          <RouterLink class="payment-return__link" :to="{ name: ROUTE_NAMES.PAYMENTS_MINE }">{{
            t('finance.return.myPayments')
          }}</RouterLink>
        </template>
      </PaymentOutcome>
    </Card>
  </section>
</template>
