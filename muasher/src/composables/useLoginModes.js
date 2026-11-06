import { computed, ref } from 'vue'
import { authService } from '@/services/authService'
import { OTP_CHANNELS, SIGN_IN_IDENTIFIER_VALUES } from '@/constants/signIn'

export function useLoginModes() {
  const modes = ref(null)

  const identifiers = computed(() => {
    const served = (modes.value?.identifiers ?? []).filter((kind) =>
      SIGN_IN_IDENTIFIER_VALUES.includes(kind),
    )
    return served.length ? served : [...SIGN_IN_IDENTIFIER_VALUES]
  })

  const codeChannels = computed(() => modes.value?.codeChannels ?? [OTP_CHANNELS.EMAIL])

  async function load() {
    try {
      modes.value = (await authService.getLoginModes()).data
    } catch {
      modes.value = null
    }
  }

  return { modes, identifiers, codeChannels, load }
}
