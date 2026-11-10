import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import JourneyHome from '@/components/portal/home/JourneyHome.vue'
import { useAuthStore } from '@/stores/auth'
import { entitiesService } from '@/services/entitiesService'
import { assessmentsService } from '@/services/assessmentsService'
import { accreditedService } from '@/services/accreditedService'
import { certificatesService } from '@/services/certificatesService'
import { dashboardService } from '@/services/dashboardService'
import { notificationsService } from '@/services/notificationsService'
import { contentService } from '@/services/contentService'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/entitiesService', () => ({ entitiesService: { getMine: vi.fn() } }))
vi.mock('@/services/assessmentsService', () => ({ assessmentsService: { list: vi.fn() } }))
vi.mock('@/services/accreditedService', () => ({
  accreditedService: { getCurrent: vi.fn(), getResult: vi.fn() },
}))
vi.mock('@/services/certificatesService', () => ({ certificatesService: { listCards: vi.fn() } }))
vi.mock('@/services/dashboardService', () => ({
  dashboardService: { getEntityBoard: vi.fn(), getActionCenter: vi.fn() },
}))
vi.mock('@/services/notificationsService', () => ({
  notificationsService: { list: vi.fn(), markRead: vi.fn() },
}))
vi.mock('@/services/contentService', () => ({
  contentService: { getCertificateLevels: vi.fn(), getPathGuide: vi.fn() },
}))

const LEVELS = {
  levels: [
    { level: 'bronze', label: { ar: 'المستوى البرونزي', en: 'Bronze level' }, minPercent: '60.00' },
    { level: 'silver', label: { ar: 'المستوى الفضي', en: 'Silver level' }, minPercent: '70.00' },
    { level: 'gold', label: { ar: 'المستوى الذهبي', en: 'Gold level' }, minPercent: '80.00' },
  ],
}

const ACTIVE = {
  id: 14,
  number: 'ACC-2026-000014',
  status: 'draft',
  createdAt: '2026-11-01T08:00:00Z',
  updatedAt: new Date().toISOString(),
  progress: {
    subsTotal: 16,
    subsComplete: 12,
    remaining: [
      {
        subCriterionId: 41,
        code: '1.3',
        name: { ar: 'ثقافة التميّز والقيادة بالقدوة', en: 'Excellence culture' },
        cvMissing: false,
        attachmentsRequired: 1,
        attachmentsUploaded: 0,
      },
    ],
  },
}

const SELF_LIST = [
  {
    id: 9001,
    status: 'result-available',
    result: { percent: '78.00' },
    completedAt: '2026-10-28T08:00:00Z',
  },
]

const ACCREDITED_LIST = [
  {
    id: 71,
    number: 'ACC-2026-000014',
    result: { percent: '82.00' },
    completedAt: '2026-10-28T08:00:00Z',
  },
  {
    id: 70,
    number: 'ACC-2025-000009',
    result: { percent: '74.00' },
    completedAt: '2025-11-02T08:00:00Z',
  },
]

function mainsOf(values) {
  return values.map(([criterionId, ar, percent]) => ({
    criterionId,
    code: String(criterionId),
    name: { ar, en: ar },
    percent,
  }))
}

const RESULTS = {
  71: {
    available: true,
    main: mainsOf([
      [1, 'القيادة الإدارية', '88.00'],
      [2, 'نتائج الموارد البشرية', '76.00'],
      [3, 'إدارة العمليات', '90.00'],
    ]),
  },
  70: {
    available: true,
    main: mainsOf([
      [1, 'القيادة الإدارية', '80.00'],
      [2, 'نتائج الموارد البشرية', '78.00'],
      [3, 'إدارة العمليات', '85.00'],
    ]),
  },
}

const GOLD = {
  id: 'c-gold',
  number: 'CRT-2026-000031',
  level: 'gold',
  status: 'issued',
  percent: '82.00',
  issuedAt: '2026-10-30T08:00:00Z',
}

let accreditedList = []

const PERIOD = {
  key: '2026-Q4',
  label: { ar: 'الربع الرابع 2026', en: 'Q4 2026' },
  startsOn: '2026-10-01',
  endsOn: '2026-12-31',
}

const PATHS = {
  self: {
    pricing: { price: null, isFree: true, availability: 'available' },
    openDraft: null,
    lastResult: {
      assessmentId: 9001,
      number: 'SLF-2026-009001',
      percent: '78.00',
      completedAt: '2026-10-28T08:00:00Z',
    },
  },
  accredited: {
    pricing: {
      price: {
        priceBeforeVat: '1000.00',
        vatRate: '15.00',
        vatAmount: '150.00',
        total: '1150.00',
        currency: 'SAR',
      },
      isFree: false,
      availability: 'available',
    },
    isLive: true,
    cadence: 'quarterly',
    currentPeriod: PERIOD,
    periodStatus: 'not-submitted',
    nextPeriod: null,
    nextSubmissionFrom: null,
    awaitingPayment: null,
    openRequest: {
      id: 14,
      number: 'ACC-2026-000014',
      status: 'draft',
      needsCompletion: false,
      completionDeadline: null,
      remainingCount: 4,
      submittedAt: null,
      periodState: 'pending',
      periodLabel: { ar: 'تُحدَّد عند الإرسال', en: 'Set on submission' },
    },
    lastResult: null,
    kit: { status: null, requestId: null },
  },
  award: {
    pricing: { price: null, isFree: true, availability: 'hidden' },
    windowState: 'none',
    cycle: null,
    nextCycle: null,
    eligibility: { eligible: false, reason: 'no_cycle' },
    openRequest: null,
    announced: false,
    lastResult: null,
  },
}

const NEXT_STEP = {
  rank: 3,
  key: 'finish_draft',
  path: 'accredited',
  target: {
    route: 'paths.accredited.request',
    params: { id: 14 },
    query: { tab: 'request' },
    href: '/paths/accredited/14?tab=request',
  },
  context: {
    assessmentId: 14,
    number: 'ACC-2026-000014',
    status: 'draft',
    paymentId: null,
    isPaid: true,
    deadlineAt: null,
    periodEndsOn: '2026-12-31',
    cycleId: null,
  },
}

function seed() {
  accreditedList = []
  entitiesService.getMine.mockResolvedValue({
    data: {
      status: 'active',
      verifiedAt: '2026-10-12T08:00:00Z',
      profileCompletion: { percent: 80 },
    },
  })
  assessmentsService.list.mockImplementation((params) =>
    Promise.resolve({ data: params['filter[path]'] === 'accredited' ? accreditedList : SELF_LIST }),
  )
  accreditedService.getResult.mockImplementation((id) => Promise.resolve({ data: RESULTS[id] }))
  accreditedService.getCurrent.mockResolvedValue({
    data: { awaitingPayment: null, active: ACTIVE, latestClosed: null },
  })
  certificatesService.listCards.mockResolvedValue({ data: [] })
  dashboardService.getEntityBoard.mockResolvedValue({
    data: {
      latestResult: {
        assessmentId: 9001,
        path: 'self',
        pathLabel: { ar: 'التقييم الذاتي', en: 'Self assessment' },
        percent: '78.00',
        completedAt: '2026-10-28T08:00:00Z',
      },
      paymentsCard: null,
      acceptedEvidenceCount: 7,
      actionCenter: [],
      level: {
        level: 'gold',
        certificate: { number: 'CRT-2026-000031', validUntil: '2027-03-31' },
      },
      paths: PATHS,
      nextStep: NEXT_STEP,
    },
  })
  dashboardService.getActionCenter.mockResolvedValue({ data: [], meta: {} })
  notificationsService.list.mockResolvedValue({ data: [], meta: { unreadCount: 0 } })
  contentService.getCertificateLevels.mockResolvedValue({ data: LEVELS })
}

async function factory(locale = 'ar') {
  const globals = createTestGlobals({ locale })
  useAuthStore(globals.pinia).setUser(sessionUserFor(['entity'], { name: 'سارة عبدالله' }))
  const wrapper = mount(JourneyHome, { global: globals })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
  seed()
})

describe('JourneyHome — uploaded evidence KPI', () => {
  it('shows the uploaded evidence count from the entity board', async () => {
    const wrapper = await factory()
    const tile = wrapper.find('[data-kpi="evidence"]')
    expect(tile.find('.kpi-tile__label').text()).toBe('الشواهد المرفوعة')
    expect(tile.find('.kpi-tile__value').text()).toBe('7')
    expect(tile.find('.kpi-tile__value').element.tagName).toBe('BDI')
  })

  it('shows a dash when the board has no evidence count, in English too', async () => {
    dashboardService.getEntityBoard.mockResolvedValue({
      data: { acceptedEvidenceCount: null, paths: PATHS, nextStep: null, level: null },
    })
    const wrapper = await factory('en')
    const tile = wrapper.find('[data-kpi="evidence"]')
    expect(tile.find('.kpi-tile__label').text()).toBe('Uploaded evidence')
    expect(tile.find('.kpi-tile__value').text()).toBe('—')
  })
})

describe('JourneyHome (D1 / M1)', () => {
  it('reads the real contracts without the legacy ?role= summary', async () => {
    await factory()
    expect(assessmentsService.list).toHaveBeenCalledWith({ 'filter[path]': 'self', perPage: 20 })
    expect(assessmentsService.list).toHaveBeenCalledWith({
      'filter[path]': 'accredited',
      'filter[status]': 'completed',
      perPage: 6,
    })
    expect(certificatesService.listCards).toHaveBeenCalledWith({
      'filter[status]': 'issued,suspended',
      perPage: 5,
    })
    expect(dashboardService.getEntityBoard).toHaveBeenCalled()
  })

  it('greets the user and shows the three path tiles without the old station path', async () => {
    const wrapper = await factory()
    expect(wrapper.find('.journey-hero__greeting').text()).toContain('أهلاً سارة')
    expect(wrapper.find('.journey-path').exists()).toBe(false)
    expect(wrapper.find('.path-tiles__title').text()).toBe('مسارات التقييم')
    const tiles = wrapper.findAll('.path-tile')
    expect(tiles.map((tile) => tile.attributes('data-state'))).toEqual(['start', 'draft'])
    expect(tiles[0].find('.path-price__free').text()).toBe('مجاني')
    expect(tiles[0].find('.path-tile__status').text()).toContain('78%')
    expect(tiles[1].find('.path-tile__line').text()).toContain('الربع الرابع 2026')
    expect(tiles[1].find('.path-tile__action').attributes('href')).toBe('/paths/accredited')
  })

  it('renders the backend next step and links into the request tab', async () => {
    const wrapper = await factory()
    const card = wrapper.find('.next-step')
    expect(card.attributes('data-rank')).toBe('3')
    expect(card.find('.next-step__title').text()).toBe('أكمل مسودة طلبك')
    expect(card.find('.next-step__text').text()).toContain('ACC-2026-000014')
    expect(card.find('.next-step__text').text()).toContain('2026-12-31')
    expect(card.find('.next-step__action--primary').attributes('href')).toBe(
      '/paths/accredited/14?tab=request',
    )
  })

  it('shows «صالحة حتى» on the level hero from the board level widget', async () => {
    certificatesService.listCards.mockImplementation((params) =>
      Promise.resolve({ data: params.perPage === 1 ? [GOLD] : [] }),
    )
    const wrapper = await factory()
    expect(wrapper.find('[data-testid="level-valid-until"]').text()).toContain('2027-03-31')
  })

  it('shows the indicative level of the self result as text only', async () => {
    const wrapper = await factory()
    const tiles = wrapper.findAll('.kpi-tile')
    expect(tiles[1].find('.kpi-tile__caption').text()).toBe(
      'المستوى الفضي (استرشادي) — لا تصدر شهادة عن التقييم الذاتي',
    )
    expect(tiles[1].find('.level-emblem').exists()).toBe(false)
  })

  it('renders in English', async () => {
    const wrapper = await factory('en')
    expect(wrapper.find('.path-tiles__title').text()).toBe('Assessment paths')
    expect(wrapper.find('.next-step__title').text()).toBe('Finish your draft')
    expect(wrapper.findAll('.kpi-tile__caption')[1].text()).toContain('Silver level (indicative)')
  })

  it('shows the no-level hero with the start action while the entity holds no certificate', async () => {
    accreditedService.getCurrent.mockResolvedValue({
      data: { awaitingPayment: null, active: null, latestClosed: null },
    })
    const wrapper = await factory()
    const hero = wrapper.find('.level-hero')
    expect(hero.attributes('data-tier')).toBe('none')
    expect(hero.find('.level-hero__name').text()).toBe('لم تحصل على مستوى بعد')
    expect(hero.find('.level-emblem__seal').exists()).toBe(false)
    expect(hero.find('.level-hero__actions a').text()).toBe('ابدأ التقييم المعتمد')
    expect(wrapper.find('.ranked-bars').exists()).toBe(false)
  })

  it('shows the gold hero with the seal, the threshold track and the remaining percent', async () => {
    certificatesService.listCards.mockImplementation((params) =>
      Promise.resolve({ data: params.perPage === 1 ? [GOLD] : [] }),
    )
    contentService.getCertificateLevels.mockResolvedValue({
      data: {
        levels: [
          ...LEVELS.levels,
          {
            level: 'diamond',
            label: { ar: 'المستوى الماسي', en: 'Diamond level' },
            minPercent: '90.00',
          },
        ],
      },
    })
    const wrapper = await factory()
    const hero = wrapper.find('.level-hero')
    expect(hero.attributes('data-tier')).toBe('gold')
    expect(hero.find('.level-emblem__seal').exists()).toBe(true)
    expect(hero.find('.level-hero__chip--reassessment').exists()).toBe(true)
    expect(hero.find('.level-hero__next').text()).toBe('تبقّى 8% لبلوغ المستوى الماسي')
    expect(hero.find('.level-hero__next bdi').attributes('dir')).toBe('ltr')
    expect(hero.findAll('.level-track__segment')).toHaveLength(5)
    expect(hero.find('.level-hero__actions a').text()).toBe('عرض الشهادة')
  })

  it('ranks the accredited criteria from 0 with deltas and compares the two latest results', async () => {
    accreditedList = ACCREDITED_LIST
    const wrapper = await factory()
    const rows = wrapper.findAll('.ranked-bars__row')
    expect(rows.map((row) => row.find('.ranked-bars__label').text())).toEqual([
      'إدارة العمليات',
      'القيادة الإدارية',
      'نتائج الموارد البشرية',
    ])
    expect(rows[0].find('.delta-chip').classes()).toContain('delta-chip--up')
    expect(rows[2].find('.delta-chip').classes()).toContain('delta-chip--down')
    expect(wrapper.find('.ranked-bars__axis').text()).toContain('0')
    expect(wrapper.find('.radar-chart__previous').exists()).toBe(true)
    expect(wrapper.find('.radar-chart__caption').text()).toBe(
      'مقارنة آخر نتيجة معتمدة بالنتيجة المعتمدة السابقة',
    )
    expect(wrapper.find('.b-card__legend-item--previous').exists()).toBe(true)
    expect(wrapper.findAll('.ranked-bars__axis-label').map((mark) => mark.text())).toEqual([
      '0',
      '25',
      '50',
      '75',
      '100',
    ])
    expect(wrapper.findAll('.ranked-bars__axis-label--minor')).toHaveLength(2)
    expect(wrapper.find('.level-trend__line').exists()).toBe(true)
    expect(wrapper.findAll('.kpi-tile')[0].find('.delta-chip').attributes('aria-label')).toBe(
      'ارتفاع بمقدار 8',
    )
  })

  it('shows the single-result caption and no dashed legend without a previous result', async () => {
    accreditedList = ACCREDITED_LIST.slice(0, 1)
    const wrapper = await factory()
    expect(wrapper.find('.radar-chart__previous').exists()).toBe(false)
    expect(wrapper.find('.radar-chart__caption').text()).toBe('النتيجة الحالية')
    expect(wrapper.find('.b-card__legend-item--previous').exists()).toBe(false)
    expect(wrapper.find('.level-trend').exists()).toBe(false)
  })

  it('shows a retry state when a required source fails', async () => {
    dashboardService.getEntityBoard.mockRejectedValueOnce(new Error('boom'))
    const wrapper = await factory()
    expect(wrapper.find('.error-state').exists()).toBe(true)
    await wrapper.find('.error-state__retry button').trigger('click')
    await flushPromises()
    expect(wrapper.find('.next-step').exists()).toBe(true)
  })
})
