import { PHONE_QUERY } from '@/constants/breakpoints'
import { useMediaQuery } from '@/composables/useMediaQuery'

export function useCompactViewport() {
  return useMediaQuery(PHONE_QUERY)
}
