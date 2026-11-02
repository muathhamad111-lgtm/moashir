import { describe, expect, it } from 'vitest'
import { useWizard } from '@/composables/useWizard'

const defs = [{ id: 'data' }, { id: 'evidence' }, { id: 'review' }]

describe('useWizard', () => {
  it('starts on the first step and navigates forward/backward within bounds', () => {
    const wizard = useWizard(defs)
    expect(wizard.currentIndex.value).toBe(0)
    expect(wizard.isFirst.value).toBe(true)
    wizard.previous()
    expect(wizard.currentIndex.value).toBe(0)
    wizard.next()
    wizard.next()
    expect(wizard.isLast.value).toBe(true)
    wizard.next()
    expect(wizard.currentIndex.value).toBe(2)
  })

  it('tracks per-step validity and completion flags', () => {
    const wizard = useWizard(defs)
    wizard.setValidity('evidence', false)
    wizard.next()
    const steps = wizard.steps.value
    expect(steps[0].complete).toBe(true)
    expect(steps[1].active).toBe(true)
    expect(steps[1].valid).toBe(false)
    expect(steps[2].complete).toBe(false)
  })

  it('jumps to a valid index only', () => {
    const wizard = useWizard(defs)
    wizard.goTo(2)
    expect(wizard.currentIndex.value).toBe(2)
    wizard.goTo(9)
    expect(wizard.currentIndex.value).toBe(2)
  })
})
