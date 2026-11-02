import { describe, expect, it, vi, beforeEach } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { useEvaluationTypeSwitch } from '@/composables/useEvaluationTypeSwitch'
import { criteriaService } from '@/services/criteriaService'
import { createTestGlobals } from '@/test-utils'

const confirm = vi.fn()

vi.mock('@/composables/useConfirm', () => ({ useConfirm: () => ({ confirm }) }))
vi.mock('@/services/criteriaService', () => ({
  criteriaService: { switchEvaluationType: vi.fn() },
}))

function harness() {
  let api
  mount(
    defineComponent({
      setup() {
        api = useEvaluationTypeSwitch()
        return () => h('div')
      },
    }),
    { global: createTestGlobals({ locale: 'en' }) },
  )
  return api
}

function pointsConfirmRequired(count) {
  return Object.assign(new Error('rejected'), {
    normalized: {
      code: 'criteria.points_confirm_required',
      meta: { impact: { pointCount: count } },
    },
  })
}

beforeEach(() => {
  vi.clearAllMocks()
  criteriaService.switchEvaluationType.mockResolvedValue({ data: { id: 11 } })
})

describe('useEvaluationTypeSwitch', () => {
  it('warns with the guiding-point count and confirms the deletion when switching to direct', async () => {
    confirm.mockResolvedValue(true)
    await harness().switchType(
      { id: 11, pointCount: 3, evaluationType: 'guiding-points' },
      'direct',
    )
    expect(confirm).toHaveBeenCalledWith(
      expect.objectContaining({
        variant: 'danger',
        message: expect.stringContaining('3 guiding points'),
      }),
    )
    expect(criteriaService.switchEvaluationType).toHaveBeenCalledWith(11, 'direct', {
      confirmDeletePoints: true,
    })
  })

  it('does nothing when the warning is dismissed', async () => {
    confirm.mockResolvedValue(false)
    expect(await harness().switchType({ id: 11, pointCount: 2 }, 'direct')).toBeNull()
    expect(criteriaService.switchEvaluationType).not.toHaveBeenCalled()
  })

  it('asks again with the server count when points appeared meanwhile', async () => {
    confirm.mockResolvedValue(true)
    criteriaService.switchEvaluationType.mockRejectedValueOnce(pointsConfirmRequired(4))
    await harness().switchType({ id: 11, pointCount: 0 }, 'direct')
    expect(confirm).toHaveBeenLastCalledWith(
      expect.objectContaining({ message: expect.stringContaining('4 guiding points') }),
    )
    expect(criteriaService.switchEvaluationType).toHaveBeenLastCalledWith(11, 'direct', {
      confirmDeletePoints: true,
    })
  })

  it('switches to guiding points without a deletion flag', async () => {
    confirm.mockResolvedValue(true)
    const reload = vi.fn()
    await harness().toggleType({ id: 12, pointCount: 0, evaluationType: 'direct' }, reload)
    expect(criteriaService.switchEvaluationType).toHaveBeenCalledWith(12, 'guiding-points', {
      confirmDeletePoints: false,
    })
    expect(reload).toHaveBeenCalled()
  })
})
