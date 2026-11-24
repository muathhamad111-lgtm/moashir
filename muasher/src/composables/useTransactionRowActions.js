import { useI18n } from 'vue-i18n'
import { usePermissions } from '@/composables/usePermissions'
import { useAuthStore } from '@/stores/auth'
import { PAYMENT_STATUSES } from '@/constants/payments'
import {
  TRANSACTION_ROW_ACTIONS as A,
  transactionRowActionKeys,
} from '@/utils/finance/transactionRowActions'

const ICONS = Object.freeze({
  [A.VIEW]: 'eye',
  [A.APPROVE]: 'check',
  [A.REJECT]: 'x',
  [A.REFUND]: 'undo',
  [A.SETTLE]: 'user-shield',
})

const VARIANTS = Object.freeze({ [A.APPROVE]: 'success', [A.REJECT]: 'danger' })

// The labelled row actions of the finance list, for the viewer signed in now.
export function useTransactionRowActions() {
  const { t } = useI18n()
  const { can } = usePermissions()
  const auth = useAuthStore()

  function labelOf(key, item) {
    if (key === A.VIEW) {
      return item.status === PAYMENT_STATUSES.PAID
        ? t('finance.transactions.viewReceipt')
        : t('finance.transactions.view')
    }
    if (key === A.APPROVE) return t('finance.transfers.approve.open')
    if (key === A.REJECT) return t('finance.transfers.reject.open')
    return t(`finance.${key}.open`)
  }

  function actionsFor(item) {
    return transactionRowActionKeys(item, { can, isSuperAdmin: auth.isSuperAdmin }).map((key) => ({
      key,
      label: labelOf(key, item),
      icon: ICONS[key],
      variant: VARIANTS[key],
    }))
  }

  return { actionsFor }
}
