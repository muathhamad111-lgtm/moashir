import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { RouterView } from 'vue-router'
import { setActivePinia } from 'pinia'
import CycleWorkspace from '@/views/award/CycleWorkspace.vue'
import AwardPageHeader from '@/components/committee/AwardPageHeader.vue'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'
import { useAuthStore } from '@/stores/auth'
import { CYCLE_WORKSPACE } from '@/utils/award/cycleWorkspace'

const service = vi.hoisted(() => ({
  get: vi.fn(),
  getDefaultConditions: vi.fn(),
  open: vi.fn(),
  closeSubmissions: vi.fn(),
  close: vi.fn(),
  reopen: vi.fn(),
}))

const committee = vi.hoisted(() => ({ listMembers: vi.fn() }))

vi.mock('@/services/awardCycleService', () => ({ awardCycleService: service }))
vi.mock('@/services/committeeService', () => ({ committeeService: committee }))
vi.mock('@/services/regionsService', () => ({
  regionsService: {
    listRegions: vi.fn().mockResolvedValue({
      data: [
        { id: 1, code: '01', name: { ar: 'منطقة الرياض', en: 'Riyadh Region' } },
        { id: 2, code: '02', name: { ar: 'منطقة مكة المكرمة', en: 'Makkah Region' } },
      ],
    }),
  },
}))

function detail(overrides = {}) {
  return {
    id: 31,
    name: { ar: 'دورة جائزة مؤشر', en: 'Moashir Award cycle' },
    description: { ar: 'تعريف الدورة', en: 'About the cycle' },
    year: 2026,
    status: 'submission-open',
    submissionStart: '2026-09-30T21:00:00Z',
    submissionEnd: '2026-12-15T20:59:00Z',
    committeeStart: '2027-01-03T21:00:00Z',
    committeeEnd: '2027-02-15T20:59:00Z',
    announcementAt: null,
    conditions: { minEntityAgeYears: 3, disqualificationScore: '60.00' },
    initialReviewSlaDays: 14,
    participationCertificateEnabled: false,
    entryFee: '0.00',
    entryFeeEditable: false,
    regions: [{ id: 1, code: '01', name: { ar: 'منطقة الرياض', en: 'Riyadh Region' } }],
    lockedRegionIds: [1],
    canManage: true,
    thresholdLocked: false,
    openBlock: null,
    revealsStaff: true,
    members: [],
    initialAssessors: [{ id: 10, name: 'مقيّم أولي', isActive: true }],
    updatedAt: '2026-11-23T10:00:00Z',
    ...overrides,
  }
}

async function mountWorkspace(
  path,
  { roles = ['system_admin'], cycle = detail(), children = false, failure = null } = {},
) {
  if (failure) service.get.mockRejectedValue(failure)
  else service.get.mockResolvedValue({ data: cycle })
  const globals = createTestGlobals({ locale: 'ar' })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles))
  await globals.router.push(path)
  await globals.router.isReady()
  const wrapper = mount(children ? RouterView : CycleWorkspace, {
    global: {
      plugins: globals.plugins,
      directives: globals.directives,
      stubs: children ? {} : { RouterView: true },
    },
    attachTo: document.body,
  })
  await flushPromises()
  return { wrapper, router: globals.router }
}

function tabKeys(wrapper) {
  return wrapper.findAll('.cycle-tabs__tab').map((tab) => tab.attributes('data-tab'))
}

function activeTab(wrapper) {
  return wrapper.find('.cycle-tabs__tab.is-active')
}

beforeEach(() => {
  vi.clearAllMocks()
  committee.listMembers.mockResolvedValue({
    data: [{ id: 5, user: { id: 27, name: 'David Nguyen' }, status: 'active' }],
    meta: { canManage: true },
  })
})

afterEach(() => {
  document.body.innerHTML = ''
})

describe('CycleWorkspace — the shared header', () => {
  it('shows the title, the status, the edit link, the stage actions and the tabs', async () => {
    const { wrapper } = await mountWorkspace('/admin/award/cycles/31')
    expect(service.get).toHaveBeenCalledWith(31)
    const header = wrapper.find('.cycle-setup__header')
    expect(header.find('h1.cycle-setup__title').text()).toBe('دورة جائزة مؤشر 2026')
    expect(header.find('.cycle-editor__back').attributes('href')).toBe('/admin/award/cycles')
    expect(header.find('.cycle-editor__back').text()).toContain('العودة إلى الدورات')
    const edit = header.find('[data-testid="cycle-edit"]')
    expect(edit.attributes('href')).toBe('/admin/award/cycles/31/setup')
    expect(edit.text()).toContain('تعديل الدورة')
    expect(header.find('.cycle-detail__actions .cycle-actions__buttons').text()).toContain(
      'إغلاق التقديم',
    )
    expect(wrapper.find('nav.cycle-tabs').attributes('aria-label')).toBe('أقسام الدورة')
    expect(tabKeys(wrapper)).toEqual(['details', 'filtering', 'committee'])
    expect(wrapper.find('nav.cycle-tabs').element.compareDocumentPosition(header.element)).toBe(
      Node.DOCUMENT_POSITION_PRECEDING,
    )
  })

  it('offers no edit link on a closed cycle or to a reader who cannot manage it', async () => {
    const closed = await mountWorkspace('/admin/award/cycles/31', {
      cycle: detail({ status: 'closed' }),
    })
    expect(closed.wrapper.find('[data-testid="cycle-edit"]').exists()).toBe(false)
    closed.wrapper.unmount()
    const reader = await mountWorkspace('/admin/award/cycles/31', {
      roles: ['request_follower'],
      cycle: detail({ canManage: false }),
    })
    expect(reader.wrapper.find('[data-testid="cycle-edit"]').exists()).toBe(false)
    expect(reader.wrapper.find('.cycle-actions__buttons button').exists()).toBe(false)
    expect(reader.wrapper.find('.cycle-actions__note').exists()).toBe(true)
  })

  it('keeps the header without actions when the cycle is missing', async () => {
    const missing = new Error('missing')
    missing.normalized = { status: 404, message: '' }
    const { wrapper } = await mountWorkspace('/admin/award/cycles/99', {
      children: true,
      failure: missing,
    })
    expect(service.get).toHaveBeenCalledTimes(1)
    expect(wrapper.findAll('h1')).toHaveLength(1)
    expect(wrapper.find('h1').text()).toBe('دورة الجائزة')
    expect(wrapper.find('.cycle-actions').exists()).toBe(false)
    expect(wrapper.find('[data-testid="cycle-edit"]').exists()).toBe(false)
    expect(wrapper.find('.empty-state').text()).toContain('الدورة غير موجودة')
  })

  it('reloads the cycle when another cycle opens in the same page', async () => {
    const { router } = await mountWorkspace('/admin/award/cycles/31')
    service.get.mockResolvedValue({ data: detail({ id: 32 }) })
    await router.push('/admin/award/cycles/32')
    await flushPromises()
    expect(service.get).toHaveBeenLastCalledWith(32)
  })
})

describe('CycleWorkspace — the tabs', () => {
  it.each([
    ['draft', ['details', 'committee']],
    ['submission-open', ['details', 'filtering', 'committee']],
    ['submission-closed', ['details', 'filtering', 'committee', 'secretary', 'results']],
    ['announced', ['details', 'filtering', 'committee', 'secretary', 'results']],
  ])('shows the stages of a %s cycle', async (status, keys) => {
    const { wrapper } = await mountWorkspace('/admin/award/cycles/31', {
      cycle: detail({ status }),
    })
    expect(tabKeys(wrapper)).toEqual(keys)
  })

  it('shows each reader the stages they may open', async () => {
    const officer = await mountWorkspace('/admin/award/cycles/31', {
      roles: ['filtering_officer'],
      cycle: detail({ status: 'submission-closed', canManage: false }),
    })
    expect(tabKeys(officer.wrapper)).toEqual(['details', 'filtering', 'committee'])
    officer.wrapper.unmount()
    const assessor = await mountWorkspace('/admin/award/cycles/31', {
      roles: ['assessor'],
      cycle: detail({ status: 'submission-closed', canManage: false }),
    })
    expect(tabKeys(assessor.wrapper)).toEqual(['details'])
  })

  it('links every tab to its stage of this cycle', async () => {
    const { wrapper } = await mountWorkspace('/admin/award/cycles/31', {
      cycle: detail({ status: 'announced' }),
    })
    expect(wrapper.findAll('.cycle-tabs__tab').map((tab) => tab.attributes('href'))).toEqual([
      '/admin/award/cycles/31',
      '/admin/award/cycles/31/filtering',
      '/admin/award/cycles/31/committee',
      '/admin/award/cycles/31/secretary',
      '/admin/award/cycles/31/results',
    ])
  })

  it.each([
    ['/admin/award/cycles/31', 'details'],
    ['/admin/award/cycles/31/filtering', 'filtering'],
    ['/admin/award/cycles/31/committee', 'committee'],
    ['/admin/award/cycles/31/secretary', 'secretary'],
    ['/admin/award/cycles/31/results', 'results'],
    ['/admin/award/cycles/31/results/national', 'results'],
  ])('marks the active tab on %s', async (path, key) => {
    const { wrapper } = await mountWorkspace(path, { cycle: detail({ status: 'announced' }) })
    expect(wrapper.findAll('.cycle-tabs__tab.is-active')).toHaveLength(1)
    expect(activeTab(wrapper).attributes('data-tab')).toBe(key)
    expect(activeTab(wrapper).attributes('aria-current')).toBe('page')
    expect(wrapper.findAll('[aria-current="page"]')).toHaveLength(1)
  })

  it('moves the active tab when another stage opens', async () => {
    const { wrapper, router } = await mountWorkspace('/admin/award/cycles/31')
    expect(activeTab(wrapper).attributes('data-tab')).toBe('details')
    await router.push('/admin/award/cycles/31/committee')
    await flushPromises()
    expect(activeTab(wrapper).attributes('data-tab')).toBe('committee')
    expect(service.get).toHaveBeenCalledTimes(1)
  })
})

describe('CycleWorkspace — the details tab', () => {
  it('opens the key facts and the detail cards under the one page title', async () => {
    const { wrapper } = await mountWorkspace('/admin/award/cycles/31', { children: true })
    expect(wrapper.findAllComponents(CycleWorkspace)).toHaveLength(1)
    expect(service.get).toHaveBeenCalledTimes(1)
    expect(wrapper.findAll('h1')).toHaveLength(1)
    expect(wrapper.findAll('.cycle-overview__fact')).toHaveLength(6)
    expect(wrapper.find('.cycle-overview__fact[data-fact="threshold"]').text()).toContain('60.00')
    expect(
      wrapper.findAll('.cycle-detail-card').map((card) => card.attributes('data-group')),
    ).toEqual(['identity', 'conditions', 'team'])
    expect(wrapper.find('.cycle-detail-card[data-group="identity"]').text()).toContain(
      'تعريف الدورة',
    )
    expect(wrapper.find('.cycle-summary').exists()).toBe(false)
    const team = wrapper.find('.cycle-detail-card[data-group="team"]')
    expect(committee.listMembers).toHaveBeenCalledWith(31)
    expect(team.text()).toContain('مقيّمو اللجنة')
    expect(team.text()).toContain('David Nguyen')
  })

  it('says no committee assessor is appointed yet on a cycle without one', async () => {
    committee.listMembers.mockResolvedValue({ data: [], meta: { canManage: true } })
    const { wrapper } = await mountWorkspace('/admin/award/cycles/31', { children: true })
    expect(wrapper.find('.cycle-detail-card[data-group="team"]').text()).toContain('لم يُعيَّن بعد')
    expect(wrapper.findAll('.cycle-detail-card__row.is-missing')).toHaveLength(0)
  })
})

describe('AwardPageHeader inside the cycle page', () => {
  const props = {
    title: 'لجنة المقيّمين',
    cycle: {
      name: { ar: 'دورة جائزة مؤشر', en: 'Moashir Award cycle' },
      year: 2026,
      status: 'closed',
    },
    back: { name: 'award.cycles' },
    backLabel: 'العودة إلى الدورات',
  }

  function mountHeader(provide = {}) {
    const globals = createTestGlobals({ locale: 'ar' })
    setActivePinia(globals.pinia)
    return mount(AwardPageHeader, { props, global: { plugins: globals.plugins, provide } })
  }

  it('drops the back link and the cycle line and becomes a section heading', () => {
    const embedded = mountHeader({ [CYCLE_WORKSPACE]: {} })
    expect(embedded.find('.cycle-editor__back').exists()).toBe(false)
    expect(embedded.find('.cycles-page__subtitle').exists()).toBe(false)
    expect(embedded.find('h1').exists()).toBe(false)
    expect(embedded.find('h2.cycles-page__title').text()).toBe('لجنة المقيّمين')
    expect(embedded.classes()).toContain('is-embedded')
  })

  it('keeps its own back link, cycle line and page title on a standalone page', () => {
    const standalone = mountHeader()
    expect(standalone.find('.cycle-editor__back').attributes('href')).toBe('/admin/award/cycles')
    expect(standalone.find('.cycles-page__subtitle').text()).toContain('دورة جائزة مؤشر')
    expect(standalone.find('h1.cycles-page__title').text()).toBe('لجنة المقيّمين')
    expect(standalone.classes()).not.toContain('is-embedded')
  })
})
