import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { identityService } from '@/services/identityService'
import { IDENTITY_CHANGE_STATUSES } from '@/constants/identityChanges'

export function useOwnIdentityChanges() {
  const { t } = useI18n()
  const toast = useToast()
  const items = ref([])
  const status = ref('idle')

  const pending = computed(
    () => items.value.find((item) => item.status === IDENTITY_CHANGE_STATUSES.PENDING) ?? null,
  )
  const decided = computed(() =>
    items.value.filter((item) => item.status !== IDENTITY_CHANGE_STATUSES.PENDING),
  )

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await identityService.myChanges()
      items.value = data ?? []
      status.value = 'success'
    } catch {
      status.value = 'error'
    }
  }

  async function submit(payload) {
    await identityService.submitChange(payload)
    toast.success(t('nationalIdentity.change.submitted'))
    await load()
    return true
  }

  async function cancel(change) {
    await identityService.cancelChange(change.id)
    toast.success(t('nationalIdentity.change.cancelled'))
    await load()
  }

  return { items, status, pending, decided, load, submit, cancel }
}
