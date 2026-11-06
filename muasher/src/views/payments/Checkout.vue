<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import TestModeNotice from '@/components/payments/TestModeNotice.vue'
import CheckoutSummary from '@/components/payments/CheckoutSummary.vue'
import CheckoutStateNotice from '@/components/payments/CheckoutStateNotice.vue'
import CheckoutChannelNotice from '@/components/payments/CheckoutChannelNotice.vue'
import MoyasarFormHost from '@/components/payments/MoyasarFormHost.vue'
import MethodChoice from '@/components/payments/MethodChoice.vue'
import CardCheckoutIntro from '@/components/payments/CardCheckoutIntro.vue'
import BankTransferPanel from '@/components/payments/BankTransferPanel.vue'
import ChangeMethodDialog from '@/components/payments/ChangeMethodDialog.vue'
import { CHECKOUT_PHASES, useCheckout } from '@/composables/useCheckout'
import { CHECKOUT_STEPS, useCheckoutChannels } from '@/composables/useCheckoutChannels'
import { useCardOrderCancel } from '@/composables/useCardOrderCancel'
import { useToast } from '@/composables/useToast'
import { useActionCenterStore } from '@/stores/actionCenter'
import { PAYMENT_CHANNELS, PAYMENT_STATUSES } from '@/constants/payments'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { financeErrorMessage } from '@/utils/payments/financeErrors'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  purpose: { type: String, required: true },
})

const i18n = useI18n()
const { t } = i18n
const route = useRoute()
const router = useRouter()
const toast = useToast()
const actionCenter = useActionCenterStore()
const flow = useCheckoutChannels(
  computed(() => props.purpose),
  { preferred: computed(() => route.query.method ?? null) },
)
const {
  phase,
  payment,
  checkout,
  failure,
  existingOrder,
  countdown,
  isStarting,
  isTest,
  start,
  applyVerified,
  reset,
} = useCheckout(props.purpose)
const changeMethod = useCardOrderCancel({
  onCancelled: () => {
    reset()
    flow.backToChoice(PAYMENT_CHANNELS.BANK_TRANSFER)
  },
})

useHead({ title: () => t(`finance.checkout.title.${props.purpose}`) })

const backRoute = computed(() =>
  props.purpose === 'bag' ? ROUTE_NAMES.BAG_DETAILS : ROUTE_NAMES.ASSESSMENT_ACCREDITED,
)
const step = computed(() => flow.step.value)
const isBankStep = computed(() => step.value === CHECKOUT_STEPS.BANK_TRANSFER)
const choosing = computed(() => step.value === CHECKOUT_STEPS.CHOOSE)
const blockedByTransfer = computed(() => Boolean(flow.openTransfer.value))
const showStart = computed(
  () => !isBankStep.value && [CHECKOUT_PHASES.IDLE, CHECKOUT_PHASES.STARTING].includes(phase.value),
)
const showForm = computed(() => phase.value === CHECKOUT_PHASES.READY)
const canChangeCard = computed(
  () =>
    flow.channels.value.includes(PAYMENT_CHANNELS.BANK_TRANSFER) &&
    payment.value?.status === PAYMENT_STATUSES.CREATED,
)
const summaryChannel = computed(() => {
  if (payment.value) return payment.value.channel ?? PAYMENT_CHANNELS.ELECTRONIC
  return choosing.value ? null : step.value
})

function onVerifyFailed(error) {
  toast.error(financeErrorMessage(i18n, error, 'finance.checkout.verifyFailed'))
}

function onContinue(channel) {
  if (channel === PAYMENT_CHANNELS.ELECTRONIC) {
    flow.continueWith(channel)
    start()
    return
  }
  flow.continueWith(channel)
}

function onSubmitted(submitted) {
  actionCenter.load()
  router.replace({ name: ROUTE_NAMES.PAYMENTS_COMPLETE, params: { id: submitted.id } })
}
</script>

<template>
  <section class="checkout">
    <header class="checkout__header">
      <p class="checkout__eyebrow">{{ t('finance.checkout.eyebrow') }}</p>
      <h1 class="checkout__title">{{ t(`finance.checkout.title.${purpose}`) }}</h1>
      <p v-if="!isBankStep" class="checkout__secured">
        <AppIcon name="lock" />
        {{ t('finance.checkout.securedBy') }}
      </p>
    </header>

    <TestModeNotice v-if="isTest" />

    <div class="checkout__layout">
      <div class="checkout__main">
        <Card>
          <Skeleton v-if="flow.status.value === 'loading'" variant="text" :count="5" />
          <ErrorState
            v-else-if="flow.status.value === 'error'"
            :message="flow.errorMessage.value"
            @retry="flow.load"
          />
          <CheckoutChannelNotice
            v-else-if="blockedByTransfer"
            kind="under-review"
            :payment-id="flow.openTransfer.value.id"
          />
          <CheckoutChannelNotice
            v-else-if="step === CHECKOUT_STEPS.UNAVAILABLE"
            kind="unavailable"
          />

          <div v-else-if="showStart" class="checkout__start">
            <MethodChoice
              v-if="choosing"
              v-model="flow.selected.value"
              :channels="flow.channels.value"
              :busy="isStarting"
              @continue="onContinue"
            >
              <template #back>
                <RouterLink class="checkout__back" :to="{ name: backRoute }">{{
                  t('finance.checkout.back')
                }}</RouterLink>
              </template>
            </MethodChoice>
            <CardCheckoutIntro v-else :busy="isStarting" :back-route="backRoute" @start="start" />
          </div>

          <BankTransferPanel
            v-else-if="isBankStep"
            :purpose="purpose"
            :accounts="flow.options.value.bankAccounts"
            :amount="flow.quote.value"
            :unified-number="flow.options.value.unifiedNumber"
            :can-change-method="flow.hasChoice.value"
            @change-method="flow.backToChoice(PAYMENT_CHANNELS.ELECTRONIC)"
            @account-unavailable="flow.reloadOptions"
            @submitted="onSubmitted"
          />

          <template v-else-if="showForm">
            <div class="checkout__form-head">
              <p v-if="existingOrder" class="checkout__existing" role="note">
                {{ t('finance.checkout.existingOrder') }}
              </p>
              <button
                v-if="canChangeCard"
                type="button"
                class="checkout__change"
                @click="changeMethod.open.value = true"
              >
                <AppIcon name="refresh" />
                {{ t('portal.checkoutMethod.changeMethod') }}
              </button>
            </div>
            <MoyasarFormHost
              :key="payment.id"
              :payment="payment"
              :checkout="checkout"
              @verified="applyVerified"
              @verify-failed="onVerifyFailed"
            />
          </template>

          <CheckoutStateNotice
            v-else
            :phase="phase"
            :payment="payment"
            :failure="failure"
            :busy="isStarting"
            @restart="start"
          />
        </Card>
      </div>

      <aside class="checkout__aside">
        <CheckoutSummary
          :purpose="purpose"
          :payment="payment"
          :quote="flow.quote.value"
          :channel="summaryChannel"
          :countdown="showForm ? countdown : null"
        />
      </aside>
    </div>

    <ChangeMethodDialog
      v-if="payment"
      v-model="changeMethod.open.value"
      :reference="payment.reference"
      :action="() => changeMethod.cancel(payment)"
    />
  </section>
</template>
