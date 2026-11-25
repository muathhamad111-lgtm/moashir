import { describe, expect, it } from 'vitest'
import { selectTabItems, tabPriorityOf } from '@/composables/useAppTabs'
import { ROLES } from '@/constants/roles'

const item = (id) => ({ id, routeName: `route.${id}` })

describe('tabPriorityOf', () => {
  it('takes the role priority list, best first', () => {
    expect(tabPriorityOf([ROLES.REQUEST_FOLLOWER])).toEqual([
      'requests',
      'bag-requests',
      'admin-entities',
    ])
  })

  it('merges several roles in role order without repeats', () => {
    expect(tabPriorityOf([ROLES.FINANCE_OFFICER, ROLES.REQUEST_FOLLOWER])).toEqual([
      'finance',
      'bag-requests',
      'admin-entities',
      'requests',
    ])
  })

  it('gives the Super Admin (no role) the system administrator list', () => {
    expect(tabPriorityOf([], true)).toEqual(['admin-entities', 'admin-users', 'settings'])
  })

  it('gives an unknown role no priorities', () => {
    expect(tabPriorityOf(['visitor'])).toEqual([])
  })
})

describe('selectTabItems', () => {
  const allowed = ['dashboard', 'requests', 'bag-requests', 'admin-entities', 'finance'].map(item)

  it('puts the dashboard first, then the allowed priority items in order', () => {
    const tabs = selectTabItems(allowed, ['bag-requests', 'requests'])
    expect(tabs.map((tab) => tab.id)).toEqual(['dashboard', 'bag-requests', 'requests'])
  })

  it('never makes a tab of an item the account may not open', () => {
    const tabs = selectTabItems(allowed, ['admin-users', 'requests'])
    expect(tabs.map((tab) => tab.id)).toEqual(['dashboard', 'requests'])
  })

  it('stops at the slot count', () => {
    const tabs = selectTabItems(
      allowed,
      ['requests', 'bag-requests', 'admin-entities', 'finance'],
      2,
    )
    expect(tabs.map((tab) => tab.id)).toEqual(['dashboard', 'requests', 'bag-requests'])
  })

  it('leaves the dashboard out when it is not allowed', () => {
    const tabs = selectTabItems([item('requests')], ['requests'])
    expect(tabs.map((tab) => tab.id)).toEqual(['requests'])
  })
})
