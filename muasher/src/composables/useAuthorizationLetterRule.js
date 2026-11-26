import { computed, ref, toValue, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useOwnEntityDocuments } from '@/composables/useOwnEntityDocuments'
import { ENTITY_DOCUMENTS } from '@/constants/entityDocuments'

const CONTACT_SECTION = 'contact'
const LETTER = ENTITY_DOCUMENTS.AUTHORIZATION_LETTER

function trimmed(value) {
  return String(value ?? '').trim()
}

/**
 * A new authorized person needs a new authorization letter in the same pending change; the form uploads the letter
 * first and only then saves the contact section.
 */
export function useAuthorizationLetterRule({ sectionKey, values, profile }) {
  const { t } = useI18n()
  const own = useOwnEntityDocuments()
  const error = ref('')

  const required = computed(
    () =>
      toValue(sectionKey) === CONTACT_SECTION &&
      trimmed(toValue(values).authorizedPersonName) !==
        trimmed(toValue(profile).authorizedPersonName),
  )
  const attached = computed(() => own.isPending(LETTER))

  watch([required, attached], () => {
    if (!required.value || attached.value) error.value = ''
  })

  function allowsSave() {
    error.value = required.value && !attached.value ? t('entities.documents.letterRequired') : ''
    return error.value === ''
  }

  return { required, attached, error, allowsSave }
}
