import { describe, expect, it } from 'vitest'
import { ref } from 'vue'
import { useCriterionSteps } from '@/composables/useCriterionSteps'

const MAINS = [{ id: 11 }, { id: 12 }, { id: 13 }]

describe('useCriterionSteps', () => {
  it('starts on the first main criterion and walks forward and back within bounds', () => {
    const steps = useCriterionSteps(ref(MAINS))
    expect(steps.current.value.id).toBe(11)
    expect(steps.isFirst.value).toBe(true)
    steps.previous()
    expect(steps.position.value).toBe(0)
    steps.next()
    steps.next()
    steps.next()
    expect(steps.current.value.id).toBe(13)
    expect(steps.isLast.value).toBe(true)
    steps.previous()
    expect(steps.current.value.id).toBe(12)
    expect(steps.isFirst.value || steps.isLast.value).toBe(false)
  })

  it('jumps to a main criterion by id and ignores unknown ids', () => {
    const steps = useCriterionSteps(ref(MAINS))
    steps.goToMain(13)
    expect(steps.position.value).toBe(2)
    steps.goToMain(99)
    expect(steps.position.value).toBe(2)
  })

  it('stays usable when the criteria are not loaded yet', () => {
    const steps = useCriterionSteps(ref([]))
    expect(steps.current.value).toBeNull()
    expect(steps.total.value).toBe(0)
  })
})
