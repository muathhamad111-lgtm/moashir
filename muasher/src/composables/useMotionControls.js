import { TABLET_UP_QUERY } from '@/constants/breakpoints'
import { useMediaQuery } from '@/composables/useMediaQuery'

export function useMotionControls() {
  return { isVisible: useMediaQuery(TABLET_UP_QUERY) }
}
