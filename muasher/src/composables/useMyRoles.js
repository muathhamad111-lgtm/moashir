import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { profileService } from '@/services/profileService'

function resolve(value, locale) {
  if (value === null || value === undefined) return ''
  if (typeof value === 'string') return value
  return value[locale] || value.ar || value.en || ''
}

// The roles granted to the signed-in user, shown read-only on the account page.
export function useMyRoles({ auto = true } = {}) {
  const { locale } = useI18n()
  const roles = ref([])
  const status = ref('loading')

  function localized(value) {
    return resolve(value, locale.value)
  }

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await profileService.getMyPermissions()
      roles.value = data.roles
      status.value = 'success'
    } catch {
      status.value = 'error'
    }
  }

  if (auto) onMounted(load)

  return { roles, status, load, localized }
}
