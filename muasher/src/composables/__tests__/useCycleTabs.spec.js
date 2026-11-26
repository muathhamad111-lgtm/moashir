import { describe, expect, it } from 'vitest'
import { nextTick, ref } from 'vue'
import { useCycleTabs } from '@/composables/useCycleTabs'
import { useAuthStore } from '@/stores/auth'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { sessionUserFor } from '@/test-utils/session'
import { withSetup } from '@/test-utils/withSetup'

function tabsFor(cycle, roles = ['system_admin'], locale = 'en') {
  const { api } = withSetup(
    () => {
      useAuthStore().setUser(sessionUserFor(roles))
      return useCycleTabs(cycle)
    },
    { locale },
  )
  return api
}

function keysOf(status, roles) {
  return tabsFor({ id: 31, status }, roles).value.map((tab) => tab.key)
}

describe('useCycleTabs — the stages of the cycle page', () => {
  it('opens a draft at its details and committee only', () => {
    expect(keysOf('draft')).toEqual(['details', 'committee'])
  })

  it('adds the filtering once submissions open', () => {
    expect(keysOf('submission-open')).toEqual(['details', 'filtering', 'committee'])
  })

  it.each([
    'submission-closed',
    'committee-assessment',
    'awaiting-secretary',
    'approved',
    'announced',
    'closed',
  ])('adds the secretary desk and the results once submissions close (%s)', (status) => {
    expect(keysOf(status)).toEqual(['details', 'filtering', 'committee', 'secretary', 'results'])
  })

  it('shows each stage only to the users who may open it', () => {
    expect(keysOf('submission-closed', ['request_follower'])).toEqual(['details', 'committee'])
    expect(keysOf('submission-closed', ['filtering_officer'])).toEqual([
      'details',
      'filtering',
      'committee',
    ])
    expect(keysOf('submission-closed', ['assessor'])).toEqual(['details'])
    expect(keysOf('submission-closed', ['award_secretary'])).toEqual([
      'details',
      'filtering',
      'committee',
      'secretary',
      'results',
    ])
  })

  it('names each tab and its route, with the national board under the results', () => {
    const tabs = tabsFor({ id: 31, status: 'announced' }).value
    expect(tabs.map((tab) => tab.label)).toEqual([
      'Details',
      'Filtering',
      'Committee',
      'Secretary desk',
      'Results',
    ])
    expect(tabs.map((tab) => tab.route)).toEqual([
      ROUTE_NAMES.AWARD_CYCLE,
      ROUTE_NAMES.AWARD_FILTERING_QUEUE,
      ROUTE_NAMES.AWARD_COMMITTEE_ROSTER,
      ROUTE_NAMES.AWARD_SECRETARY_DESK,
      ROUTE_NAMES.AWARD_RESULTS_BOARD,
    ])
    expect(tabs.find((tab) => tab.key === 'results').names).toEqual([
      ROUTE_NAMES.AWARD_RESULTS_BOARD,
      ROUTE_NAMES.AWARD_NATIONAL,
    ])
    expect(tabs.find((tab) => tab.key === 'details').names).toEqual([ROUTE_NAMES.AWARD_CYCLE])
  })

  it('words the tabs in Arabic', () => {
    expect(
      tabsFor({ id: 31, status: 'submission-open' }, ['system_admin'], 'ar').value.map(
        (tab) => tab.label,
      ),
    ).toEqual(['التفاصيل', 'التصفية', 'لجنة المقيّمين'])
  })

  it('follows the stage of the cycle as it changes', async () => {
    const cycle = ref({ id: 31, status: 'draft' })
    const tabs = tabsFor(cycle)
    expect(tabs.value.map((tab) => tab.key)).toEqual(['details', 'committee'])
    cycle.value = { id: 31, status: 'submission-open' }
    await nextTick()
    expect(tabs.value.map((tab) => tab.key)).toEqual(['details', 'filtering', 'committee'])
    cycle.value = { id: 31, status: 'closed' }
    await nextTick()
    expect(tabs.value.map((tab) => tab.key)).toContain('results')
  })
})
