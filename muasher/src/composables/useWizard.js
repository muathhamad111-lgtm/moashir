import { computed, reactive, ref } from 'vue'

export function useWizard(stepDefinitions) {
  const currentIndex = ref(0)
  const validity = reactive({})

  const steps = computed(() =>
    stepDefinitions.map((step, index) => ({
      ...step,
      valid: validity[step.id] ?? null,
      complete: index < currentIndex.value,
      active: index === currentIndex.value,
    })),
  )

  const currentStep = computed(() => steps.value[currentIndex.value])
  const isFirst = computed(() => currentIndex.value === 0)
  const isLast = computed(() => currentIndex.value === stepDefinitions.length - 1)

  function setValidity(stepId, isValid) {
    validity[stepId] = isValid
  }

  function goTo(index) {
    if (index < 0 || index >= stepDefinitions.length) return
    currentIndex.value = index
  }

  function next() {
    if (!isLast.value) currentIndex.value += 1
  }

  function previous() {
    if (!isFirst.value) currentIndex.value -= 1
  }

  return { steps, currentIndex, currentStep, isFirst, isLast, setValidity, goTo, next, previous }
}
