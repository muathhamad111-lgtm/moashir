import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUnsavedGuard } from '@/composables/useUnsavedGuard'

export function useProfileEditor() {
  const { t } = useI18n()
  const active = ref(null)
  const focusField = ref(null)
  const savedSection = ref(null)
  const dirty = ref(false)

  function close() {
    active.value = null
    focusField.value = null
    dirty.value = false
  }

  const { allowLeaving } = useUnsavedGuard(dirty, {
    onDiscard: close,
    message: () =>
      t('portal.profile.unsavedSection', {
        section: t(`portal.profile.sections.${active.value}`),
      }),
  })

  async function open(section, field = null) {
    if (active.value === section) {
      focusField.value = field
      return true
    }
    if (!(await allowLeaving())) return false
    active.value = section
    focusField.value = field
    savedSection.value = null
    return true
  }

  function cancel() {
    close()
  }

  function markSaved(section) {
    savedSection.value = section
    close()
  }

  function setDirty(value) {
    dirty.value = value
  }

  return { active, focusField, savedSection, open, cancel, markSaved, setDirty }
}
