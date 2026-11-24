import { describe, expect, it } from 'vitest'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { transactionRowActionKeys } from '@/utils/finance/transactionRowActions'

function viewer(permissions = [], isSuperAdmin = false) {
  return { can: (permission) => permissions.includes(permission), isSuperAdmin }
}

const REVIEWER = viewer([PERMISSIONS.FINANCE_TRANSFERS_REVIEW, PERMISSIONS.FINANCE_REFUNDS_MANAGE])
const pendingTransfer = { channel: 'bank_transfer', status: 'under-review' }

describe('finance row actions', () => {
  it('lets a reviewer approve or reject a transfer waiting for approval', () => {
    expect(transactionRowActionKeys(pendingTransfer, REVIEWER)).toEqual([
      'view',
      'approve',
      'reject',
    ])
  })

  it('shows a pending transfer read only to a viewer without the review permission', () => {
    expect(transactionRowActionKeys(pendingTransfer, viewer())).toEqual(['view'])
  })

  it('offers a refund on a paid operation once, to a viewer who manages refunds', () => {
    const paid = { channel: 'electronic', status: 'paid', hasRefund: false }
    expect(transactionRowActionKeys(paid, REVIEWER)).toEqual(['view', 'refund'])
    expect(transactionRowActionKeys({ ...paid, hasRefund: true }, REVIEWER)).toEqual(['view'])
    expect(transactionRowActionKeys(paid, viewer())).toEqual(['view'])
  })

  it('lets only the Super Admin settle an open electronic payment by hand', () => {
    const open = { channel: 'electronic', status: 'pending' }
    expect(transactionRowActionKeys(open, viewer([], true))).toEqual(['view', 'settle'])
    expect(transactionRowActionKeys(open, REVIEWER)).toEqual(['view'])
    expect(
      transactionRowActionKeys({ channel: 'bank_transfer', status: 'created' }, viewer([], true)),
    ).toEqual(['view'])
  })
})
