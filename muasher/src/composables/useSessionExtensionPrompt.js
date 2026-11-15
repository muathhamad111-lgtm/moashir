import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { lastServerActivityAt } from '@/api/sessionEvents'
import { SESSION_PROMPT } from '@/constants/session'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { countdownText, promptDueAt } from '@/utils/sessionPrompt'
import { logger } from '@/utils/logger'

export function useSessionExtensionPrompt({ saveBeforeSignOut }) {
  const { t } = useI18n()
  const router = useRouter()
  const auth = useAuthStore()
  const toast = useToast()
  const open = ref(false)
  const busy = ref(false)
  const now = ref(Date.now())
  const windowStartedAt = ref(Date.now())
  const deadline = ref(0)
  let timer = null

  const countdown = computed(() => countdownText(deadline.value - now.value))

  function dueAt() {
    return promptDueAt({
      windowStartedAt: windowStartedAt.value,
      lastActivityAt: lastServerActivityAt(),
      idleMinutes: auth.user?.idleTimeoutMinutes ?? null,
    })
  }

  async function extend() {
    if (busy.value) return
    busy.value = true
    logger.breadcrumb('session', 'session.extend')
    await auth.refreshSession()
    windowStartedAt.value = Date.now()
    open.value = false
    busy.value = false
  }

  async function signOut() {
    if (busy.value) return
    busy.value = true
    logger.breadcrumb('session', 'session.saveAndSignOut')
    await saveBeforeSignOut()
    const redirect = router.currentRoute.value.fullPath
    await auth.logout()
    open.value = false
    busy.value = false
    toast.info(t('auth.session.savedAndSignedOut'))
    await router.push({ name: ROUTE_NAMES.AUTH_LOGIN, query: { redirect } })
  }

  function tick() {
    now.value = Date.now()
    if (!auth.isAuthenticated || busy.value) return
    if (open.value && now.value >= deadline.value) return signOut()
    if (!open.value && now.value >= dueAt()) {
      deadline.value = now.value + SESSION_PROMPT.GRACE_MS
      open.value = true
    }
  }

  onMounted(() => {
    timer = setInterval(tick, SESSION_PROMPT.CHECK_MS)
  })

  onBeforeUnmount(() => clearInterval(timer))

  return { open, busy, countdown, extend, signOut }
}
