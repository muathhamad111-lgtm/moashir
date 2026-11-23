import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import NdaStatusCell from '@/components/nda/NdaStatusCell.vue'
import NdaRoleWarning from '@/components/nda/NdaRoleWarning.vue'
import { ndaPublishPayload, ndaRoleWarningApplies } from '@/utils/nda/ndaForms'
import { createTestGlobals } from '@/test-utils'

const SCOPE = ['system_admin', 'assessor']

describe('agreement column and role warning', () => {
  it('shows the accepted version, pending or not in scope in the users list', () => {
    const globals = createTestGlobals({ locale: 'en' })
    const accepted = mount(NdaStatusCell, {
      props: { status: 'accepted', version: '1.1' },
      global: globals,
    })
    const pending = mount(NdaStatusCell, {
      props: { status: 'pending', version: '1.1' },
      global: globals,
    })
    const outside = mount(NdaStatusCell, { props: { status: 'out-of-scope' }, global: globals })

    expect(accepted.text()).toBe('1.1')
    expect(pending.text()).toBe('Pending')
    expect(outside.text()).toBe('Not in scope')
  })

  it('warns when an in-scope role is added to a user without a current acceptance', () => {
    expect(
      ndaRoleWarningApplies({
        scopeRoles: SCOPE,
        addedRoles: ['assessor'],
        status: 'out-of-scope',
      }),
    ).toBe(true)
    expect(
      ndaRoleWarningApplies({ scopeRoles: SCOPE, addedRoles: ['assessor'], status: 'accepted' }),
    ).toBe(false)
    expect(
      ndaRoleWarningApplies({ scopeRoles: SCOPE, addedRoles: ['finance_officer'], status: null }),
    ).toBe(false)
  })

  it('names the user in the edit dialog and speaks of the invitation otherwise', () => {
    const globals = createTestGlobals({ locale: 'en' })
    const edit = mount(NdaRoleWarning, {
      props: {
        scopeRoles: SCOPE,
        initialRoles: ['finance_officer'],
        selectedRoles: ['finance_officer', 'assessor'],
        status: 'out-of-scope',
        name: 'Reem',
      },
      global: globals,
    })
    const invite = mount(NdaRoleWarning, {
      props: { scopeRoles: SCOPE, selectedRoles: ['assessor'] },
      global: globals,
    })
    const none = mount(NdaRoleWarning, {
      props: { scopeRoles: SCOPE, initialRoles: ['assessor'], selectedRoles: ['assessor'] },
      global: globals,
    })

    expect(edit.text()).toContain('Reem will be asked to accept')
    expect(invite.text()).toContain('when accepting the invitation')
    expect(none.find('[data-testid="nda-role-warning"]').exists()).toBe(false)
  })

  it('builds the publish payload with trimmed text and the confirmation', () => {
    expect(
      ndaPublishPayload({
        changeSummary: { ar: ' تغيير ', en: ' Change ' },
        reason: ' Legal review. ',
        confirmIrreversible: true,
      }),
    ).toEqual({
      changeSummary: { ar: 'تغيير', en: 'Change' },
      reason: 'Legal review.',
      confirmIrreversible: true,
    })
  })
})
