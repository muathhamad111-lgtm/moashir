import { SORT_DIRECTIONS } from '@/utils/listSort'

// The operations table's columns, in order.
export function transactionColumns(t) {
  return [
    { key: 'operation', label: t('finance.transactions.columns.operation') },
    { key: 'entity', label: t('finance.fields.entity') },
    {
      key: 'total',
      label: t('finance.transactions.columns.amount'),
      sortKey: 'total',
      sortFirst: SORT_DIRECTIONS.DESC,
    },
    {
      key: 'payment',
      label: t('finance.transactions.columns.payment'),
    },
    { key: 'status', label: t('finance.fields.status') },
    {
      key: 'createdAt',
      label: t('finance.transactions.columns.date'),
      sortKey: 'created_at',
      sortFirst: SORT_DIRECTIONS.DESC,
      date: true,
    },
    { key: 'receipt', label: t('finance.transactions.columns.receipt') },
    { key: 'beforeVat', label: t('finance.pricing.beforeVatShort') },
    { key: 'actions', label: t('components.actions.label') },
  ]
}
