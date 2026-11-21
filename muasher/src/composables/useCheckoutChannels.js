import { computed, onMounted, ref, unref } from 'vue'
import { paymentsService } from '@/services/paymentsService'
import { PAYMENT_CHANNELS, PAYMENT_STATUSES } from '@/constants/payments'

export const CHECKOUT_STEPS = Object.freeze({
  CHOOSE: 'choose',
  ELECTRONIC: PAYMENT_CHANNELS.ELECTRONIC,
  BANK_TRANSFER: PAYMENT_CHANNELS.BANK_TRANSFER,
  UNAVAILABLE: 'unavailable',
})

const EMPTY_OPTIONS = Object.freeze({
  channels: [],
  bankAccounts: [],
  unifiedNumber: null,
  pricing: null,
})

export function initialStepOf(channels) {
  if (!channels.length) return CHECKOUT_STEPS.UNAVAILABLE
  if (channels.length > 1) return CHECKOUT_STEPS.CHOOSE
  return channels[0]
}

async function openTransferOf(purpose) {
  const { data } = await paymentsService.listMine({
    'filter[status]': PAYMENT_STATUSES.UNDER_REVIEW,
    'filter[purpose]': purpose,
    perPage: 1,
  })
  return data?.[0] ?? null
}

export function useCheckoutChannels(order, { preferred = null } = {}) {
  const status = ref('loading')
  const errorMessage = ref('')
  const options = ref({ ...EMPTY_OPTIONS })
  const quote = ref(null)
  const openTransfer = ref(null)
  const step = ref(CHECKOUT_STEPS.CHOOSE)
  const selected = ref(null)

  const channels = computed(() => options.value.channels)
  const hasChoice = computed(() => channels.value.length > 1)

  function preferredChannel() {
    const wanted = unref(preferred)
    return channels.value.includes(wanted) ? wanted : channels.value[0]
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const [checkoutOptions, transfer] = await Promise.all([
        paymentsService.checkoutOptions(unref(order)),
        openTransferOf(unref(order).purpose),
      ])
      options.value = checkoutOptions.data
      quote.value = checkoutOptions.data.pricing?.price ?? null
      openTransfer.value = transfer
      step.value = initialStepOf(channels.value)
      selected.value = preferredChannel() ?? null
      status.value = 'ready'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  async function reloadOptions() {
    try {
      options.value = (await paymentsService.checkoutOptions(unref(order))).data
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
    }
  }

  function continueWith(channel) {
    if (!channels.value.includes(channel)) return
    step.value = channel
  }

  function backToChoice(channel = selected.value) {
    if (!hasChoice.value) return
    selected.value = channels.value.includes(channel) ? channel : preferredChannel()
    step.value = CHECKOUT_STEPS.CHOOSE
  }

  onMounted(load)

  return {
    status,
    errorMessage,
    options,
    quote,
    openTransfer,
    step,
    selected,
    channels,
    hasChoice,
    load,
    reloadOptions,
    continueWith,
    backToChoice,
  }
}
