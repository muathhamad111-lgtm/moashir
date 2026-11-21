import { useDeferredVisibility } from './useDeferredVisibility'
import { useNavigationState } from '@/router/navigationState'

// Whether a layout covers its content with the waiting page: only while a navigation to
// another page outlasts the short delay, and then for at least the minimum time.
export function usePageLoading() {
  const { isLoadingPage } = useNavigationState()
  return useDeferredVisibility(isLoadingPage)
}
