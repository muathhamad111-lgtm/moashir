import { describe, expect, it, vi } from 'vitest'
import { useFormSteps } from '@/composables/useFormSteps'

const WIZARD = {
  steps: ['basics', 'weight', 'review'],
  stepFields: { basics: ['nameAr', 'nameEn'], weight: ['weight'] },
}

function formWith(invalid = []) {
  return {
    validateField: vi.fn(async (field) => ({ valid: !invalid.includes(field) })),
  }
}

describe('useFormSteps', () => {
  it('moves on only when every field of the current step is valid', async () => {
    const form = formWith(['nameEn'])
    const steps = useFormSteps(form, WIZARD)
    await steps.next()
    expect(steps.step.value).toBe('basics')
    expect(form.validateField.mock.calls.map(([field]) => field)).toEqual(['nameAr', 'nameEn'])
  })

  it('validates nothing on a step without fields and stops at the last step', async () => {
    const form = formWith()
    const steps = useFormSteps(form, WIZARD)
    await steps.next()
    await steps.next()
    expect(steps.step.value).toBe('review')
    form.validateField.mockClear()
    await steps.next()
    expect(steps.step.value).toBe('review')
    expect(form.validateField).not.toHaveBeenCalled()
  })

  it('goes back without validating and never before the first step', async () => {
    const form = formWith()
    const steps = useFormSteps(form, WIZARD)
    await steps.next()
    form.validateField.mockClear()
    steps.back()
    steps.back()
    expect(steps.step.value).toBe('basics')
    expect(form.validateField).not.toHaveBeenCalled()
  })

  it('brings back the first step that holds an error', async () => {
    const steps = useFormSteps(formWith(), WIZARD)
    await steps.next()
    await steps.next()
    steps.showErrors(['weight', 'basics'])
    expect(steps.step.value).toBe('basics')
    steps.showErrors([])
    expect(steps.step.value).toBe('basics')
  })
})
