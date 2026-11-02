<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import TestModeNotice from '@/components/payments/TestModeNotice.vue'
import CheckoutSummary from '@/components/payments/CheckoutSummary.vue'
import CheckoutStateNotice from '@/components/payments/CheckoutStateNotice.vue'
import MoyasarFormHost from '@/components/payments/MoyasarFormHost.vue'
import { CHECKOUT_PHASES, useCheckout } from '@/composables/useCheckout'
import { useToast } from '@/composables/useToast'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { financeErrorMessage } from '@/utils/payments/financeErrors'

const props = defineProps({
  purpose: { type: String, required: true },
})

const i18n = useI18n()
const { t } = i18n
const toast = useToast()
const { phase, payment, checkout, failure, existingOrder, countdown, isStarting, isTest, start, applyVerified } = useCheckout(props.purpose)

useHead({ title: () => t(`finance.checkout.title.${props.purpose}`) })

const backRoute = computed(() => (props.purpose === 'bag' ? ROUTE_NAMES.BAG_DETAILS : ROUTE_NAMES.ASSESSMENT_ACCREDITED))
const showStart = computed(() => [CHECKOUT_PHASES.IDLE, CHECKOUT_PHASES.STARTING].includes(phase.value))
const showForm = computed(() => phase.value === CHECKOUT_PHASES.READY)

function onVerifyFailed(error) {
  toast.error(financeErrorMessage(i18n, error, 'finance.checkout.verifyFailed'))
}
</script>

<template>
  <section class="checkout">
    <header class="checkout__header">
      <p class="checkout__eyebrow">{{ t('finance.checkout.eyebrow') }}</p>
      <h1 class="checkout__title">{{ t(`finance.checkout.title.${purpose}`) }}</h1>
      <p class="checkout__secured">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
        {{ t('finance.checkout.securedBy') }}
      </p>
    </header>

    <TestModeNotice v-if="isTest" />

    <div class="checkout__layout">
      <div class="checkout__main">
        <Card>
          <div v-if="showStart" class="checkout__start">
            <p class="checkout__intro">{{ t('finance.checkout.intro') }}</p>
            <p class="checkout__card-note" role="note">
              <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
              {{ t('finance.checkout.cardNote') }}
            </p>
            <div class="checkout__actions">
              <BaseButton variant="primary" icon="fa-solid fa-credit-card" :loading="isStarting" :disabled="isStarting" @click="start">
                {{ t('finance.checkout.start') }}
              </BaseButton>
              <RouterLink class="checkout__back" :to="{ name: backRoute }">{{ t('finance.checkout.back') }}</RouterLink>
            </div>
          </div>

          <template v-else-if="showForm">
            <p v-if="existingOrder" class="checkout__existing" role="note">{{ t('finance.checkout.existingOrder') }}</p>
            <MoyasarFormHost :key="payment.id" :payment="payment" :checkout="checkout" @verified="applyVerified" @verify-failed="onVerifyFailed" />
          </template>

          <CheckoutStateNotice v-else :phase="phase" :payment="payment" :failure="failure" :busy="isStarting" @restart="start" />
        </Card>
      </div>

      <aside class="checkout__aside">
        <CheckoutSummary :purpose="purpose" :payment="payment" :countdown="showForm ? countdown : null" />
      </aside>
    </div>
  </section>
</template>
