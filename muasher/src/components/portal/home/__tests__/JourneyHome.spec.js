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
vi.mock('@/services/accreditedService', () => ({ accreditedService: { getCurrent: vi.fn() } }))
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

function seed() {
  entitiesService.getMine.mockResolvedValue({
    data: {
      status: 'active',
      verifiedAt: '2026-10-12T08:00:00Z',
      profileCompletion: { percent: 80 },
    },
  })
  assessmentsService.list.mockResolvedValue({
    data: [
      {
        id: 9001,
        status: 'result-available',
        result: { percent: '78.00' },
        completedAt: '2026-10-28T08:00:00Z',
      },
    ],
  })
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
      actionCenter: [],
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

describe('JourneyHome (D1 / M1)', () => {
  it('reads the real contracts without the legacy ?role= summary', async () => {
    await factory()
    expect(assessmentsService.list).toHaveBeenCalledWith({ 'filter[path]': 'self', perPage: 20 })
    expect(certificatesService.listCards).toHaveBeenCalledWith({
      'filter[status]': 'issued,suspended',
      perPage: 5,
    })
    expect(dashboardService.getEntityBoard).toHaveBeenCalled()
  })

  it('greets the user and places the entity at station 3 with the self result', async () => {
    const wrapper = await factory()
    expect(wrapper.find('.journey-hero__title').text()).toContain('أهلاً سارة')
    expect(wrapper.find('.journey-hero__lead').text()).toContain('78%')
    expect(wrapper.find('.journey-hero__lead bdi').text()).toBe('78%')
    expect(wrapper.find('.journey-path__badge').text()).toBe('المحطة 3 من 4')
    const stations = wrapper.findAll('.journey-station')
    expect(
      stations.map((station) =>
        station.classes().find((name) => name.startsWith('journey-station--')),
      ),
    ).toEqual([
      'journey-station--done',
      'journey-station--done',
      'journey-station--current',
      'journey-station--upcoming',
    ])
    expect(stations[2].attributes('aria-current')).toBe('step')
    expect(stations[2].find('.journey-station__meta bdi').text()).toBe('12 / 16')
  })

  it('shows the evidence next step with progress and the remaining sub-criteria', async () => {
    const wrapper = await factory()
    const card = wrapper.find('.next-step')
    expect(card.find('.next-step__title').text()).toBe('أكمل شواهد التقييم المعتمد')
    expect(card.find('.next-step__text').text()).toContain('بقي معيار فرعي واحد')
    expect(card.find('[role="progressbar"]').attributes('aria-valuenow')).toBe('12')
    expect(card.find('.remaining-subs__item').text()).toContain('ثقافة التميّز')
    expect(card.find('.next-step__action--primary').attributes('href')).toBe(
      '/assessment/accredited/form/14',
    )
  })

  it('shows the indicative level of the self result as text only', async () => {
    const wrapper = await factory()
    const result = wrapper.find('.glance-result__note')
    expect(result.text()).toBe('المستوى الفضي (استرشادي) — لا تصدر شهادة عن التقييم الذاتي')
    expect(wrapper.find('.glance-certificate__seal').exists()).toBe(false)
    expect(wrapper.find('.glance-certificate__upcoming').text()).toContain('2026')
  })

  it('renders in English', async () => {
    const wrapper = await factory('en')
    expect(wrapper.find('.journey-path__badge').text()).toBe('Station 3 of 4')
    expect(wrapper.find('.glance-result__note').text()).toContain('Silver level (indicative)')
  })

  it('shows a retry state when a required source fails', async () => {
    accreditedService.getCurrent.mockRejectedValueOnce(new Error('boom'))
    const wrapper = await factory()
    expect(wrapper.find('.error-state').exists()).toBe(true)
    accreditedService.getCurrent.mockResolvedValueOnce({
      data: { awaitingPayment: null, active: ACTIVE, latestClosed: null },
    })
    await wrapper.find('.error-state__retry button').trigger('click')
    await flushPromises()
    expect(wrapper.find('.next-step').exists()).toBe(true)
  })
})
