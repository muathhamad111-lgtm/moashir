import { computed, nextTick, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useWizard } from '@/composables/useWizard'
import { REGISTRATION_STEPS } from '@/constants/entityRegistration'

function firstStepWithErrors(errors) {
  return REGISTRATION_STEPS.findIndex((step) => step.fields.some((field) => errors[field]))
}

function errorsOf(step, errors) {
  return step.fields.filter((field) => errors[field]).map((field) => [field, errors[field]])
}

export function useRegistrationSteps(form) {
  const { t } = useI18n()
  const wizard = useWizard(REGISTRATION_STEPS)

  const steps = computed(() =>
    wizard.steps.value.map((step) => ({ ...step, label: t(step.labelKey) })),
  )

  async function validateCurrentStep() {
    const step = wizard.currentStep.value
    const results = await Promise.all(step.fields.map((field) => form.validateField(field)))
    const isValid = results.every((result) => result.valid)
    wizard.setValidity(step.id, isValid)
    return isValid
  }

  async function goTo(index) {
    if (index <= wizard.currentIndex.value) {
      wizard.goTo(index)
      return true
    }
    const isValid = await validateCurrentStep()
    if (isValid) wizard.goTo(index)
    return isValid
  }

  watch(form.errors, async (errors) => {
    const index = firstStepWithErrors(errors)
    if (index === -1 || index >= wizard.currentIndex.value) return
    const step = REGISTRATION_STEPS[index]
    const stepErrors = errorsOf(step, errors)
    wizard.setValidity(step.id, false)
    wizard.goTo(index)
    await nextTick()
    stepErrors.forEach(([field, message]) => form.setFieldError(field, message))
  })

  return {
    steps,
    currentIndex: wizard.currentIndex,
    currentStep: wizard.currentStep,
    isLast: wizard.isLast,
    goTo,
    next: () => goTo(wizard.currentIndex.value + 1),
    previous: wizard.previous,
  }
}
