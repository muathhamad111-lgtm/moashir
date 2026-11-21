import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFormSections } from '@/composables/useFormSections'
import { useFormSteps } from '@/composables/useFormSteps'
import { fieldStepsOf } from '@/utils/criteriaFormSections'

// Where a criteria form shows its fields: all at once (a dialog), one tab at a time (the editor sheet,
// `props.section`) or one step at a time (a create wizard, `props.wizard`).
export function useCriteriaFormLayout(
  props,
  form,
  emit,
  { sheetSections, wizard, attachments, record },
) {
  const { t } = useI18n()
  const steps = useFormSteps(form, wizard)
  const inWizard = () => Boolean(props.wizard)

  const sections = useFormSections(
    {
      section: () => props.section || (inWizard() ? wizard.views[steps.step.value] : ''),
      inSheet: () => Boolean(props.section),
      fieldSections: inWizard() ? fieldStepsOf(wizard) : sheetSections,
      attachments,
      record,
    },
    form,
    emit,
  )

  watch(sections.errorSections, (list) => {
    if (inWizard()) steps.showErrors(list)
  })

  const stepItems = computed(() =>
    wizard.steps.map((value) => ({ value, label: t(`criteria.wizard.steps.${value}`) })),
  )

  const shellProps = computed(() => {
    if (props.inline) return { readonly: props.readonly }
    if (inWizard())
      return {
        modelValue: props.modelValue,
        steps: stepItems.value,
        step: steps.step.value,
        error: form.formError.value,
      }
    return { modelValue: props.modelValue, size: 'lg' }
  })

  return { sections, steps, shellProps, inWizard }
}
