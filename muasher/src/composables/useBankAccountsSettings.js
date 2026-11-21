import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { paymentChannelsService } from '@/services/paymentChannelsService'
import { pendingTransfersOf } from '@/services/mappers/bankTransfers'
import { BANK_TRANSFER_CODES } from '@/constants/bankTransfer'
import { errorCodeOf, financeErrorMessage } from '@/utils/payments/financeErrors'

const LIST_PARAMS = { perPage: 100 }
const DIRECTIONS = { up: -1, down: 1 }

export const RETIRE_ACTIONS = Object.freeze({ DEACTIVATE: 'deactivate', DELETE: 'delete' })

export function movedIds(accounts, accountId, direction) {
  const ids = accounts.map((account) => account.id)
  const index = ids.indexOf(accountId)
  const target = index + DIRECTIONS[direction]
  if (index === -1 || target < 0 || target >= ids.length) return null
  const next = [...ids]
  ;[next[index], next[target]] = [next[target], next[index]]
  return next
}

export function useBankAccountsSettings({ reasonExtra, onChanged = () => {} }) {
  const i18n = useI18n()
  const toast = useToast()
  const status = ref('loading')
  const errorMessage = ref('')
  const accounts = ref([])
  const busyId = ref(null)
  const blocked = ref(null)

  const isEmpty = computed(() => status.value === 'success' && accounts.value.length === 0)

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      accounts.value = (await paymentChannelsService.listAccounts(LIST_PARAMS)).data
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  async function reload() {
    try {
      accounts.value = (await paymentChannelsService.listAccounts(LIST_PARAMS)).data
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
    }
  }

  async function changed(messageKey) {
    toast.success(i18n.t(messageKey))
    await reload()
    onChanged()
  }

  function failed(error, account, action) {
    if (errorCodeOf(error) === BANK_TRANSFER_CODES.PENDING_TRANSFERS) {
      blocked.value = { account, action, transfers: pendingTransfersOf(error) }
      return
    }
    toast.error(financeErrorMessage(i18n, error, 'settings.bankAccounts.actionFailed'))
  }

  async function run(account, action, request, messageKey) {
    const extra = await reasonExtra()
    if (extra === null) return false
    busyId.value = account?.id ?? 'new'
    try {
      await request(extra.reason)
      await changed(messageKey)
      return true
    } catch (error) {
      failed(error, account, action)
      return false
    } finally {
      busyId.value = null
    }
  }

  async function create(values) {
    const extra = await reasonExtra()
    if (extra === null) return null
    const { data } = await paymentChannelsService.createAccount(values, extra.reason)
    await changed('settings.bankAccounts.toasts.added')
    return data
  }

  async function saveNote(account, payerNote) {
    const extra = await reasonExtra()
    if (extra === null) return null
    const { data } = await paymentChannelsService.updateNote(account.id, payerNote, extra.reason)
    await changed('settings.bankAccounts.toasts.noteSaved')
    return data
  }

  function deactivate(account) {
    return run(
      account,
      RETIRE_ACTIONS.DEACTIVATE,
      (reason) => paymentChannelsService.deactivate(account.id, reason),
      'settings.bankAccounts.toasts.deactivated',
    )
  }

  function activate(account) {
    return run(
      account,
      'activate',
      (reason) => paymentChannelsService.activate(account.id, reason),
      'settings.bankAccounts.toasts.activated',
    )
  }

  function remove(account) {
    const messageKey = account.isUsed
      ? 'settings.bankAccounts.toasts.archived'
      : 'settings.bankAccounts.toasts.deleted'
    return run(
      account,
      RETIRE_ACTIONS.DELETE,
      (reason) => paymentChannelsService.remove(account.id, reason),
      messageKey,
    )
  }

  function move(account, direction) {
    const ids = movedIds(accounts.value, account.id, direction)
    if (!ids) return Promise.resolve(false)
    return run(
      account,
      'reorder',
      (reason) => paymentChannelsService.reorder(ids, reason),
      'settings.bankAccounts.toasts.reordered',
    )
  }

  function closeBlocked() {
    blocked.value = null
  }

  return {
    status,
    errorMessage,
    accounts,
    busyId,
    blocked,
    isEmpty,
    load,
    reload,
    create,
    saveNote,
    deactivate,
    activate,
    remove,
    move,
    closeBlocked,
  }
}
