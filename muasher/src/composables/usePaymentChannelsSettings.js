import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { paymentChannelsService } from '@/services/paymentChannelsService'
import { CHANNEL_CHOICES } from '@/constants/bankTransfer'
import { PAYMENT_CHANNELS } from '@/constants/payments'
import { financeErrorMessage } from '@/utils/payments/financeErrors'

const CHOICE_CHANNELS = Object.freeze({
  [CHANNEL_CHOICES.ELECTRONIC]: [PAYMENT_CHANNELS.ELECTRONIC],
  [CHANNEL_CHOICES.BANK_TRANSFER]: [PAYMENT_CHANNELS.BANK_TRANSFER],
  [CHANNEL_CHOICES.BOTH]: [PAYMENT_CHANNELS.ELECTRONIC, PAYMENT_CHANNELS.BANK_TRANSFER],
})

export const CHANNEL_BLOCKERS = Object.freeze({
  NO_ACCOUNT: 'noAccount',
  GATEWAY_NOT_READY: 'gatewayNotReady',
})

export function choiceOf(channels = []) {
  const hasElectronic = channels.includes(PAYMENT_CHANNELS.ELECTRONIC)
  const hasTransfer = channels.includes(PAYMENT_CHANNELS.BANK_TRANSFER)
  if (hasElectronic && hasTransfer) return CHANNEL_CHOICES.BOTH
  return hasTransfer ? CHANNEL_CHOICES.BANK_TRANSFER : CHANNEL_CHOICES.ELECTRONIC
}

export function channelsOfChoice(choice) {
  return [...(CHOICE_CHANNELS[choice] ?? CHOICE_CHANNELS[CHANNEL_CHOICES.ELECTRONIC])]
}

export function usePaymentChannelsSettings({ reasonExtra }) {
  const i18n = useI18n()
  const toast = useToast()
  const status = ref('loading')
  const errorMessage = ref('')
  const view = ref(null)
  const choice = ref(CHANNEL_CHOICES.ELECTRONIC)
  const saving = ref(false)
  const saveError = ref('')

  const chosenChannels = computed(() => channelsOfChoice(choice.value))
  const savedChoice = computed(() => (view.value ? choiceOf(view.value.channels) : null))
  const dirty = computed(() => savedChoice.value !== null && savedChoice.value !== choice.value)
  const blocker = computed(() => {
    if (!view.value) return null
    if (
      chosenChannels.value.includes(PAYMENT_CHANNELS.BANK_TRANSFER) &&
      view.value.activeBankAccountsCount === 0
    )
      return CHANNEL_BLOCKERS.NO_ACCOUNT
    if (chosenChannels.value.includes(PAYMENT_CHANNELS.ELECTRONIC) && !view.value.gatewayReady)
      return CHANNEL_BLOCKERS.GATEWAY_NOT_READY
    return null
  })
  const noReviewer = computed(
    () =>
      Boolean(view.value) &&
      chosenChannels.value.includes(PAYMENT_CHANNELS.BANK_TRANSFER) &&
      view.value.reviewersCount === 0,
  )
  const electronicOffered = computed(() =>
    Boolean(view.value?.availableChannels.includes(PAYMENT_CHANNELS.ELECTRONIC)),
  )
  const canSave = computed(() => dirty.value && !blocker.value && !saving.value)

  function apply(data, { keepChoice = false } = {}) {
    view.value = data
    if (!keepChoice) choice.value = choiceOf(data.channels)
  }

  function select(next) {
    choice.value = next
    saveError.value = ''
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      apply((await paymentChannelsService.getChannels()).data)
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  async function refresh() {
    try {
      apply((await paymentChannelsService.getChannels()).data, { keepChoice: dirty.value })
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
    }
  }

  async function save() {
    if (!canSave.value) return
    const extra = await reasonExtra()
    if (extra === null) return
    saving.value = true
    saveError.value = ''
    try {
      const { data } = await paymentChannelsService.updateChannels(
        chosenChannels.value,
        extra.reason,
      )
      apply(data)
      toast.success(i18n.t('settings.paymentChannels.saved'))
    } catch (error) {
      saveError.value = financeErrorMessage(i18n, error, 'settings.paymentChannels.saveFailed')
    } finally {
      saving.value = false
    }
  }

  return {
    status,
    errorMessage,
    view,
    choice,
    saving,
    saveError,
    dirty,
    blocker,
    noReviewer,
    electronicOffered,
    canSave,
    select,
    load,
    refresh,
    save,
  }
}
