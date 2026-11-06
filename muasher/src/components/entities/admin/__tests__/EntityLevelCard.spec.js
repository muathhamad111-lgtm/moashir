import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import EntityLevelCard from '@/components/entities/admin/EntityLevelCard.vue'
import { certificatesService } from '@/services/certificatesService'

vi.mock('@/services/certificatesService', () => ({
  certificatesService: { listCards: vi.fn() },
}))

const ISSUED = {
  id: 'c3',
  number: 'CRT-2026-000031',
  level: 'gold',
  status: 'issued',
  percent: '82.00',
  issuedAt: '2026-10-30T08:00:00Z',
}

async function render(props = {}) {
  const wrapper = mount(EntityLevelCard, {
    props: { entityId: 6001, ...props },
    global: createTestGlobals(),
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => vi.clearAllMocks())

describe('EntityLevelCard (staff)', () => {
  it('reads the certificates of the entity and shows the ribbon and the level history', async () => {
    certificatesService.listCards.mockResolvedValue({
      data: [
        ISSUED,
        { ...ISSUED, id: 'c2', number: 'CRT-2025-000009', level: 'silver', status: 'superseded' },
        { ...ISSUED, id: 'c1', number: 'CRT-2024-000017', level: null, status: 'revoked' },
      ],
    })
    const wrapper = await render()
    expect(certificatesService.listCards).toHaveBeenCalledWith({
      'filter[entityId]': 6001,
      perPage: 20,
    })
    expect(wrapper.find('[data-testid="level-ribbon"]').attributes('data-tier')).toBe('gold')
    expect(wrapper.findAll('.level-history__item')).toHaveLength(3)
    expect(wrapper.find('.level-history__chip--current').text()).toBe('الحالية')
  })

  it('shows no level when the only certificate is revoked', async () => {
    certificatesService.listCards.mockResolvedValue({
      data: [{ ...ISSUED, status: 'revoked' }],
    })
    const wrapper = await render()
    expect(wrapper.find('[data-testid="level-ribbon"]').attributes('data-tier')).toBe('none')
  })

  it('adds the re-assessment chip to a metal level with an open accredited request', async () => {
    certificatesService.listCards.mockResolvedValue({ data: [ISSUED] })
    const wrapper = await render({ openAccredited: true })
    const ribbon = wrapper.find('[data-testid="level-ribbon"]')
    expect(ribbon.attributes('data-tier')).toBe('gold')
    expect(ribbon.find('[data-testid="level-reassessment"]').text()).toBe('قيد إعادة التقييم')
  })

  it('shows in progress, without the chip, for an entity with no level and an open request', async () => {
    certificatesService.listCards.mockResolvedValue({ data: [] })
    const wrapper = await render({ openAccredited: true })
    const ribbon = wrapper.find('[data-testid="level-ribbon"]')
    expect(ribbon.attributes('data-tier')).toBe('progress')
    expect(ribbon.find('[data-testid="level-reassessment"]').exists()).toBe(false)
  })

  it('shows no chip without an open accredited request', async () => {
    certificatesService.listCards.mockResolvedValue({ data: [ISSUED] })
    const wrapper = await render()
    expect(wrapper.find('[data-testid="level-reassessment"]').exists()).toBe(false)
  })

  it('shows a retry on failure and then the empty history', async () => {
    certificatesService.listCards.mockRejectedValueOnce(new Error('offline'))
    const wrapper = await render()
    expect(wrapper.find('.error-state').exists()).toBe(true)
    certificatesService.listCards.mockResolvedValueOnce({ data: [] })
    await wrapper.find('.error-state__retry button').trigger('click')
    await flushPromises()
    expect(wrapper.find('.entity-level__empty').text()).toBe('لا توجد شهادات صادرة لهذه الجهة بعد.')
  })
})
