import { useAuthStore } from '@/stores/auth'
import { usePermissions } from '@/composables/usePermissions'
import { accreditedService } from '@/services/accreditedService'
import { isPortalAudience } from '@/utils/portalAudience'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ACCREDITED_STATUSES } from '@/constants/accredited'

const EDITABLE_STATES = [ACCREDITED_STATUSES.DRAFT, ACCREDITED_STATUSES.NEEDS_COMPLETION]

export function accreditedFormRedirect(current) {
  const active = current?.active
  if (!active?.id || !EDITABLE_STATES.includes(active.status)) return null
  return { name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_FORM, params: { id: String(active.id) } }
}

function managesOwnAccredited() {
  const auth = useAuthStore()
  return (
    isPortalAudience(auth.roles) && usePermissions().can(PERMISSIONS.ASSESSMENT_ACCREDITED_MANAGE)
  )
}

export async function accreditedEntryGuard() {
  if (!managesOwnAccredited()) return true
  try {
    const { data } = await accreditedService.getCurrent()
    return accreditedFormRedirect(data) ?? true
  } catch {
    return true
  }
}
