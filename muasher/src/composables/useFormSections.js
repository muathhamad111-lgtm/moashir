import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { attachmentsEdited } from '@/utils/criteriaAttachments'

// One criteria form split over the editor sheet's tabs: it shows one section at a time but keeps all
// its values, so a single save covers every tab. Errors are reported per section, so the sheet can
// point at the tab that holds them. Unsaved edits (values or attachment rows that differ from the record)
// are reported as `dirty`, so the sheet can ask before they are dropped.
// `section` may return one section or several (a wizard step can show a few). `inSheet` tells the
// editor sheet apart from a wizard or a plain dialog.
export function useFormSections(
  { section, fieldSections, attachments, record, inSheet: sheet = null },
  form,
  emit,
) {
  const { t } = useI18n()
  const inSheet = computed(() => (sheet ? sheet() : Boolean(section())))
  const title = computed(() =>
    section()
      ? t(`criteria.workspace.sheet.tabs.${section()}`)
      : t('criteria.workspace.details.dataTitle'),
  )
  const shows = (name) => !section() || [section()].flat().includes(name)

  const errorSections = computed(() => {
    const clientKeys = Object.entries(form.errors.value)
      .filter(([, message]) => Boolean(message))
      .map(([key]) => key)
    const serverKeys = Object.keys(form.serverErrors.value ?? {})
    const sections = [...clientKeys, ...serverKeys].map((key) =>
      key.startsWith('attachments') ? 'attachments' : fieldSections[key],
    )
    return [...new Set(sections.filter(Boolean))]
  })

  watch(errorSections, (list) => emit('section-errors', list))
  // A save in flight is not a loss: closing then keeps the edits, and a failed save makes them dirty again.
  const isDirty = computed(
    () =>
      !form.submitting.value &&
      (form.meta.value.dirty || attachmentsEdited(attachments.value, record()?.attachments)),
  )
  watch(isDirty, (dirty) => emit('dirty', dirty), { immediate: true })

  return { inSheet, shows, title, errorSections }
}
