import { describe, expect, it } from 'vitest'
import {
  isMobileTaskScreen,
  isPortalAudience,
  isTaskScreen,
  layoutKeyFor,
  portalAreaOf,
} from '@/utils/portalAudience'
import { ROUTE_NAMES as R } from '@/constants/routeNames'

describe('portal audience and screens', () => {
  it('treats only entity-only accounts as the portal audience', () => {
    expect(isPortalAudience(['entity'])).toBe(true)
    expect(isPortalAudience(['entity', 'assessor'])).toBe(false)
    expect(isPortalAudience(['system_admin'])).toBe(false)
    expect(isPortalAudience([])).toBe(false)
  })

  it('switches only the app layout', () => {
    expect(layoutKeyFor('app', ['entity'])).toBe('portal')
    expect(layoutKeyFor('app', ['system_admin'])).toBe('app')
    expect(layoutKeyFor('public', ['entity'])).toBe('public')
    expect(layoutKeyFor('auth', ['entity'])).toBe('auth')
  })

  it('maps routes to their portal area', () => {
    expect(portalAreaOf(R.APP_DASHBOARD)).toBe('journey')
    expect(portalAreaOf(R.ASSESSMENT_ACCREDITED_FORM)).toBe('paths')
    expect(portalAreaOf(R.PATHS_ACCREDITED_REQUEST)).toBe('paths')
    expect(portalAreaOf(R.PAYMENTS_COMPLETE)).toBe('payments')
    expect(portalAreaOf(R.MESSAGES_THREAD)).toBe('messages')
    expect(portalAreaOf(R.NOTIFICATIONS_INDEX)).toBe('more')
    expect(portalAreaOf(R.ADMIN_SETTINGS)).toBeNull()
  })

  it('knows task screens and the mobile-only task screens', () => {
    expect(isTaskScreen(R.ASSESSMENT_ACCREDITED_FORM)).toBe(true)
    expect(isTaskScreen(R.PAYMENTS_COMPLETE)).toBe(true)
    expect(isTaskScreen(R.APP_DASHBOARD)).toBe(false)
    expect(isTaskScreen(R.MESSAGES_THREAD)).toBe(false)
    expect(isMobileTaskScreen(R.MESSAGES_THREAD)).toBe(true)
  })
})
