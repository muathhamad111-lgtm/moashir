import { computed } from 'vue'
import { useWizard } from '@/composables/useWizard'

// The steps of a create wizard over one vee-validate form: Next validates only the fields of the
// current step, Back never validates, and an error found later (on submit or from the server)
// brings back the first step that holds one.
export function useFormSteps(form, { steps, stepFields }) {
  const wizard = useWizard(steps.map((id) => ({ id })))
  const step = computed(() => wizard.currentStep.value.id)

  async function next() {
    const fields = stepFields[step.value] ?? []
    const results = await Promise.all(fields.map((field) => form.validateField(field)))
    if (results.every((result) => result.valid)) wizard.next()
  }

  function showErrors(sections) {
    const first = steps.findIndex((name) => sections.includes(name))
    if (first >= 0) wizard.goTo(first)
  }

  // A reopened wizard starts again from its first step.
  function reset() {
    wizard.goTo(0)
  }

  return { step, next, back: wizard.previous, showErrors, reset }
}
