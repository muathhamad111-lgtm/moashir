import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { ref } from 'vue'
import RejectTransferDialog from '@/components/finance/RejectTransferDialog.vue'
import ApproveTransferDialog from '@/components/finance/ApproveTransferDialog.vue'
import TransferQueueTable from '@/components/finance/TransferQueueTable.vue'
import TransferBulkBar from '@/components/finance/TransferBulkBar.vue'
import TransferQueueFilters from '@/components/finance/TransferQueueFilters.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import { TRANSFER_FILTERS } from '@/constants/bankTransfer'
import BulkOutcomeNotice from '@/components/finance/BulkOutcomeNotice.vue'
import TransferDecisionLine from '@/components/finance/TransferDecisionLine.vue'
import PendingTransfersDialog from '@/components/settings/payment/PendingTransfersDialog.vue'
import { useTransferSelection } from '@/composables/useTransferSelection'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import { createTestGlobals } from '@/test-utils'

const TARGET = {
  id: 'p-1',
  reference: 'PAY-2026-000021',
  entityName: { ar: 'مؤسسة نماء للتنمية', en: 'Namaa Development Foundation' },
  total: '1150.00',
  currency: 'SAR',
}

function transfer(id, overrides = {}) {
  return {
    id,
    reference: `PAY-2026-0000${id}`,
    purpose: 'accredited',
    status: 'under-review',
    isTest: true,
    pricing: { total: '1150.00', currency: 'SAR' },
    entity: { name: { ar: 'جمعية', en: 'Society' }, isFrozen: false },
    bankAccount: { bankName: { ar: 'بنك المثال', en: 'Example Bank' } },
    submittedAt: '2026-11-05T08:42:00Z',
    hasDuplicateProof: false,
    ...overrides,
  }
}

function mountWith(component, props, { roles = [], extra = {} } = {}) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore().setUser(sessionUserFor(roles, extra))
  return mount(component, {
    props,
    global: { ...globals, stubs: { teleport: true } },
    attachTo: document.body,
  })
}

async function settle() {
  for (let round = 0; round < 10; round += 1) {
    await flushPromises()
    await new Promise((resolve) => setTimeout(resolve, 10))
  }
}

describe('RejectTransferDialog', () => {
  it('requires a preset and a note for another reason, then sends one decision', async () => {
    const action = vi.fn().mockResolvedValue()
    const wrapper = mountWith(RejectTransferDialog, { modelValue: true, targets: [TARGET], action })
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(wrapper.text()).toContain('Choose a rejection reason.')
    await wrapper.find('input[value="other"]').setValue(true)
    await settle()
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(wrapper.text()).toContain('Write a note for the entity when you choose "Other reason".')
    expect(action).not.toHaveBeenCalled()
    await wrapper.find('textarea[name="note"]').setValue('The receipt names another beneficiary.')
    await settle()
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(action).toHaveBeenCalledWith({
      rejectionReason: 'other',
      note: 'The receipt names another beneficiary.',
      reason: null,
    })
    expect(wrapper.emitted('update:modelValue')).toEqual([[false]])
    wrapper.unmount()
  })

  it('titles a bulk rejection with the count and asks the Super Admin for a reason', async () => {
    const wrapper = mountWith(RejectTransferDialog, {
      modelValue: true,
      targets: [TARGET, { ...TARGET, id: 'p-2', reference: 'PAY-2026-000022' }],
      asksReason: true,
      action: vi.fn(),
    })
    expect(wrapper.find('.modal__title').text()).toBe('Reject the selected transfers (2)')
    expect(wrapper.find('textarea[name="reason"]').exists()).toBe(true)
    wrapper.unmount()
  })
})

describe('ApproveTransferDialog', () => {
  it('confirms without fields for a reviewer and with a reason for the Super Admin', async () => {
    const action = vi.fn().mockResolvedValue()
    const reviewer = mountWith(ApproveTransferDialog, {
      modelValue: true,
      targets: [TARGET],
      action,
    })
    expect(reviewer.find('textarea').exists()).toBe(false)
    expect(reviewer.find('[role="alertdialog"] .modal__title').text()).toBe('Approve the transfer?')
    expect(reviewer.find('.modal__message').text()).toBe('PAY-2026-000021')
    await reviewer.find('.modal__footer .btn--primary').trigger('click')
    await settle()
    expect(action).toHaveBeenCalledWith(null)
    reviewer.unmount()
    const superAdmin = mountWith(ApproveTransferDialog, {
      modelValue: true,
      targets: [TARGET],
      asksReason: true,
      action,
    })
    await superAdmin.find('.modal__footer .btn--primary').trigger('click')
    await settle()
    expect(action).toHaveBeenCalledTimes(1)
    await superAdmin.find('textarea[name="reason"]').setValue('Approved after the statement check.')
    await superAdmin.find('.modal__footer .btn--primary').trigger('click')
    await settle()
    expect(action).toHaveBeenLastCalledWith('Approved after the statement check.')
    superAdmin.unmount()
  })
})

describe('TransferQueueTable', () => {
  it('shows checkboxes for reviewers only and disables frozen entities', async () => {
    const items = ref([transfer('21'), transfer('22', { entity: { name: {}, isFrozen: true } })])
    const globals = createTestGlobals({ locale: 'en' })
    setActivePinia(globals.pinia)
    let selection
    const wrapper = mount(
      {
        components: { TransferQueueTable },
        setup() {
          selection = useTransferSelection(items, { isSelectable: (item) => !item.entity.isFrozen })
          return { items, selection }
        },
        template: `<TransferQueueTable :items="items" status="success" selectable :selection="selection"
          :is-decidable="(item) => !item.entity.isFrozen" empty-title="Empty" />`,
      },
      { global: globals },
    )
    const boxes = wrapper.findAll('.transfer-queue__table tbody input[type="checkbox"]')
    expect(boxes.map((box) => box.attributes('disabled') !== undefined)).toEqual([false, true])
    await wrapper.find('.transfer-queue__table thead input[type="checkbox"]').setValue(true)
    expect(selection.selectedIds.value).toEqual(['21'])
    expect(wrapper.findAll('.transfer-flags .badge').map((badge) => badge.text())).toContain(
      'Frozen entity',
    )
    const readOnly = mount(TransferQueueTable, {
      props: { items: items.value, status: 'success', emptyTitle: 'Empty' },
      global: globals,
    })
    expect(readOnly.find('input[type="checkbox"]').exists()).toBe(false)
  })
})

describe('TransferQueueFilters', () => {
  it('names each account by bank and last 4 digits, with the state when it is not active', async () => {
    const accounts = [
      {
        id: 'a-1',
        bankName: { ar: 'بنك المثال', en: 'Example Bank' },
        ibanLast4: '7519',
        state: 'active',
      },
      {
        id: 'a-2',
        bankName: { ar: 'مصرف العيّنة', en: 'Sample Bank' },
        ibanLast4: '7890',
        state: 'inactive',
      },
      {
        id: 'a-3',
        bankName: { ar: 'مصرف الواحة', en: 'Al-Waha Bank' },
        ibanLast4: '0011',
        state: 'archived',
      },
    ]
    const wrapper = mountWith(TransferQueueFilters, {
      filters: { ...TRANSFER_FILTERS },
      sort: 'submitted_at',
      accounts,
    })
    const accountSelect = wrapper.findAllComponents(AppSelect)[0]
    expect(accountSelect.props('options')).toEqual([
      { value: '', label: 'All accounts' },
      { value: 'a-1', label: 'Example Bank · ends in 7519' },
      { value: 'a-2', label: 'Sample Bank · ends in 7890 (Inactive)' },
      { value: 'a-3', label: 'Al-Waha Bank · ends in 0011 (Archived)' },
    ])
    accountSelect.vm.$emit('update:modelValue', 'a-3')
    await flushPromises()
    expect(wrapper.emitted('filter')).toEqual([['bankAccount', 'a-3']])
    wrapper.unmount()
  })
})

describe('TransferBulkBar and the outcome', () => {
  it('names the selected count, disables actions over the limit and reports skipped items', () => {
    const bar = mountWith(TransferBulkBar, { count: 3, overLimit: true })
    expect(bar.text()).toContain('Approve selected (3)')
    expect(
      bar
        .findAll('.transfer-bulk-bar__actions .btn')
        .every((btn) => btn.attributes('disabled') !== undefined),
    ).toBe(true)
    const outcome = mountWith(BulkOutcomeNotice, {
      outcome: { decided: ['p-1'], skipped: [{ id: 'p-2', code: 'entity.deleted' }] },
      references: { 'p-2': 'PAY-2026-000022' },
    })
    expect(outcome.text()).toContain('decided: 1')
    expect(outcome.text()).toContain('PAY-2026-000022 skipped: the entity is frozen.')
  })
})

describe('TransferDecisionLine', () => {
  it('names the reviewer for staff and shows the Super Admin generically', () => {
    const decided = mountWith(TransferDecisionLine, {
      transfer: {
        status: 'rejected',
        reviewedAt: '2026-11-02T07:05:00Z',
        reviewedBy: { id: 22, name: 'سعود بن مبارك الدواسر' },
        rejectionReason: 'not_received',
        reviewNote: 'The amount has not arrived.',
      },
    })
    expect(decided.text()).toContain('Rejected by سعود بن مبارك الدواسر')
    expect(decided.text()).toContain('Reason: Not found in the bank statement')
    expect(decided.find('.transfer-decision-line__note bdi').text()).toBe(
      'The amount has not arrived.',
    )
    const superAdmin = mountWith(TransferDecisionLine, {
      transfer: {
        status: 'paid',
        reviewedAt: '2026-11-02T07:05:00Z',
        reviewedBy: { id: 900, name: null },
      },
    })
    expect(superAdmin.text()).toContain('Approved by Super Admin')
  })
})

describe('PendingTransfersDialog', () => {
  const blocked = {
    action: 'delete',
    account: {
      bankName: { ar: 'بنك المثال', en: 'Example Bank' },
      iban: 'SA0380000000608010167519',
    },
    transfers: [
      {
        paymentId: 'p-1',
        reference: 'PAY-2026-000021',
        entityName: { ar: 'مؤسسة نماء', en: 'Namaa' },
        total: '1150.00',
        currency: 'SAR',
        submittedAt: '2026-11-03T06:20:00Z',
      },
    ],
  }

  it('lists the waiting transfers read-only for a user without the review permission', () => {
    const wrapper = mountWith(PendingTransfersDialog, { blocked, canReview: false })
    expect(wrapper.text()).toContain("The account can't be deleted now")
    expect(wrapper.text()).toContain('A transfer reviewer must approve or reject it first.')
    expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('lets a reviewer select the transfers and shows the bulk actions', async () => {
    const wrapper = mountWith(PendingTransfersDialog, { blocked, canReview: true })
    expect(wrapper.find('.transfer-bulk-bar').exists()).toBe(false)
    await wrapper.find('tbody input[type="checkbox"]').setValue(true)
    expect(wrapper.find('.transfer-bulk-bar').text()).toContain('1 of 1 selected')
    wrapper.unmount()
  })
})
