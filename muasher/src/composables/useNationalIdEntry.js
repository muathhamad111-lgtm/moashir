import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useAuthStore } from '@/stores/auth'
import { identityService } from '@/services/identityService'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { nationalIdRule } from '@/utils/identity/nationalIdSchema'
import { normalizeNationalId } from '@/utils/identity/nationalId'
import { object } from 'yup'

function safeRedirect(value) {
  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//')
    ? value
    : null
}

export function useNationalIdEntry() {
  const { t } = useI18n()
  const auth = useAuthStore()
  const route = useRoute()
  const router = useRouter()

  const form = useValidatedForm({
    schema: computed(() => object({ nationalId: nationalIdRule(t) })),
    initialValues: { nationalId: '' },
    action: 'nationalIdentity.complete.submit',
    submit: async (values) => {
      const { data } = await identityService.provideNationalId(
        normalizeNationalId(values.nationalId),
      )
      auth.applyNationalId(data.nationalIdMasked)
      await router.replace(
        safeRedirect(route.query.redirect) ?? { name: ROUTE_NAMES.APP_DASHBOARD },
      )
    },
  })

  async function signOut() {
    await auth.logout()
    await router.replace({ name: ROUTE_NAMES.AUTH_LOGIN })
  }

  return { form, signOut }
}
