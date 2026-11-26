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

beforeEach(() => {
  vi.clearAllMocks()
  criteriaService.switchEvaluationType.mockResolvedValue({ data: { id: 11 } })
})

describe('useEvaluationTypeSwitch', () => {
  it('says the indicators stay without weights when switching to direct', async () => {
    confirm.mockResolvedValue(true)
    await harness().switchType(
      { id: 11, pointCount: 3, evaluationType: 'guiding-points' },
      'direct',
    )
    expect(confirm).toHaveBeenCalledWith(
      expect.objectContaining({
        variant: 'confirm',
        message: expect.stringContaining('indicators and their attachments stay'),
      }),
    )
    expect(criteriaService.switchEvaluationType).toHaveBeenCalledWith(11, 'direct')
  })

  it('does nothing when the confirmation is dismissed', async () => {
    confirm.mockResolvedValue(false)
    expect(await harness().switchType({ id: 11, pointCount: 2 }, 'direct')).toBeNull()
    expect(criteriaService.switchEvaluationType).not.toHaveBeenCalled()
  })

  it('explains the equal split when switching back to indicators, then reloads', async () => {
    confirm.mockResolvedValue(true)
    const reload = vi.fn()
    await harness().toggleType({ id: 12, pointCount: 2, evaluationType: 'direct' }, reload)
    expect(confirm).toHaveBeenCalledWith(
      expect.objectContaining({ message: expect.stringContaining('split equally') }),
    )
    expect(criteriaService.switchEvaluationType).toHaveBeenCalledWith(12, 'guiding-points')
    expect(reload).toHaveBeenCalled()
  })
})
