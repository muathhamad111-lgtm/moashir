import { MOCK_MODULES as M } from '@/constants/mockModules'
import { kpisHandlers } from './kpis'
import { notificationsHandlers } from './notifications'
import { criteriaHandlers } from './criteria'
import { contentHandlers } from './content'
import { aiHandlers } from './ai'
import { usersHandlers } from './users'
import { authHandlers } from './auth'
import { entitiesHandlers } from './entities'
import { entityProfileChangesHandlers } from './entityProfileChanges'
import { entityLifecycleHandlers } from './entityLifecycle'
import { entityEmailChangesHandlers } from './entityEmailChanges'
import { privacyRequestsHandlers } from './privacyRequests'
import { regionsHandlers } from './regions'
import { assessmentsHandlers } from './assessments'
import { knowledgeBaseHandlers, platformSettingsHandlers } from './settings'
import { notificationTypesHandlers } from './notificationTypes'
import { certificateSettingsHandlers } from './certificateSettings'
import { integrationsHandlers } from './integrations'
import { paymentIntegrationHandlers } from './paymentIntegration'
import { smsIntegrationHandlers } from './smsIntegration'
import { emailIntegrationHandlers } from './emailIntegration'
import { aiIntegrationHandlers } from './aiIntegration'
import { paymentsHandlers } from './payments'
import { financeHandlers } from './finance'
import { bankTransfersHandlers } from './bankTransfers'
import { paymentChannelsHandlers } from './paymentChannels'
import { financeSettingsHandlers } from './financeSettings'
import { moyasarSandboxHandlers } from './moyasarSandbox'
import { messagingHandlers } from './messaging'
import { accreditedHandlers } from './accredited'
import { requestsHandlers } from './requests'
import { assessorReviewHandlers } from './assessorReview'
import { insightsHandlers } from './insights'
import { assessmentSettingsHandlers } from './assessmentSettings'
import { certificatesHandlers } from './certificates'
import { certificatesStaffHandlers } from './certificatesStaff'
import { servicePricesHandlers } from './servicePrices'
import { bagHandlers } from './bag'
import { bagRequestsHandlers } from './bagRequests'
import { awardHandlers } from './award'
import { committeeHandlers } from './committee'
import { secretaryHandlers } from './secretary'
import { awardResultsHandlers } from './awardResults'
import { permissionsHandlers } from './permissions'
import { dashboardHandlers } from './dashboard'
import { entityBoardHandlers } from './entityBoard'
import { brandingHandlers } from './branding'
import { profileHandlers } from './profile'
import { searchHandlers } from './search'
import { sanctumHandlers } from './sanctum'
import { devSessionHandlers } from './devSession'
import { contactMessagesHandlers } from './contactMessages'
import { contactInboxHandlers } from './contactInbox'
import { contactSettingsHandlers } from './contactSettings'
import { certificateLevelsHandlers } from './certificateLevels'
import { pathGuidesHandlers } from './pathGuides'
import { legalHandlers } from './legal'
import { legalVersionsHandlers } from './legalVersions'
import { auditLogsHandlers } from './auditLogs'

export const CORE_MOCK_KEYS = Object.freeze({ SESSION: 'session', AUTH: 'auth' })

export const mockModules = [
  {
    key: CORE_MOCK_KEYS.SESSION,
    core: true,
    handlers: [...sanctumHandlers, ...(import.meta.env.DEV ? devSessionHandlers() : [])],
  },
  { key: M.KPIS, handlers: kpisHandlers },
  { key: M.NOTIFICATIONS, handlers: notificationsHandlers },
  { key: M.CRITERIA, handlers: criteriaHandlers },
  {
    key: M.CMS,
    handlers: [
      ...legalHandlers,
      ...legalVersionsHandlers,
      ...contentHandlers,
      ...certificateLevelsHandlers,
      ...pathGuidesHandlers,
    ],
  },
  {
    key: M.CONTACT,
    handlers: [...contactInboxHandlers, ...contactSettingsHandlers, ...contactMessagesHandlers],
  },
  { key: M.AI, handlers: [...aiHandlers, ...knowledgeBaseHandlers] },
  { key: M.USERS, handlers: usersHandlers },
  { key: CORE_MOCK_KEYS.AUTH, core: true, handlers: authHandlers },
  {
    key: M.ENTITIES,
    handlers: [
      ...entityEmailChangesHandlers,
      ...entityLifecycleHandlers,
      ...entityProfileChangesHandlers,
      ...entitiesHandlers,
      ...privacyRequestsHandlers,
      ...regionsHandlers,
    ],
  },
  { key: M.AWARD, handlers: awardHandlers },
  { key: M.ASSESSMENTS, handlers: assessmentsHandlers },
  {
    key: M.INTEGRATIONS,
    handlers: [
      ...integrationsHandlers,
      ...paymentIntegrationHandlers,
      ...smsIntegrationHandlers,
      ...emailIntegrationHandlers,
      ...aiIntegrationHandlers,
    ],
  },
  { key: M.PLATFORM_SETTINGS, handlers: platformSettingsHandlers },
  { key: M.NOTIFICATIONS_ADMIN, handlers: notificationTypesHandlers },
  { key: M.PAYMENTS, handlers: [...paymentsHandlers, ...moyasarSandboxHandlers] },
  {
    key: M.FINANCE,
    handlers: [
      ...bankTransfersHandlers,
      ...paymentChannelsHandlers,
      ...financeHandlers,
      ...financeSettingsHandlers,
      ...servicePricesHandlers,
    ],
  },
  { key: M.MESSAGING, handlers: messagingHandlers },
  {
    key: M.ACCREDITED,
    handlers: [
      ...accreditedHandlers,
      ...requestsHandlers,
      ...assessorReviewHandlers,
      ...assessmentSettingsHandlers,
      ...insightsHandlers,
    ],
  },
  {
    key: M.CERTIFICATES,
    handlers: [
      ...certificatesStaffHandlers,
      ...certificatesHandlers,
      ...certificateSettingsHandlers,
    ],
  },
  { key: M.BAG, handlers: [...bagHandlers, ...bagRequestsHandlers] },
  { key: M.RESULTS, handlers: awardResultsHandlers },
  { key: M.COMMITTEE, handlers: committeeHandlers },
  { key: M.SECRETARY, handlers: secretaryHandlers },
  { key: M.PERMISSIONS, handlers: permissionsHandlers },
  { key: M.AUDIT, handlers: auditLogsHandlers },
  { key: M.ENTITY_BOARD, handlers: entityBoardHandlers },
  { key: M.DASHBOARDS, handlers: dashboardHandlers },
  { key: M.BRANDING, handlers: brandingHandlers },
  { key: M.PROFILE, handlers: profileHandlers },
  { key: M.SEARCH, handlers: searchHandlers },
]

export function handlersOfModules(keys) {
  const selected = new Set(keys)
  return mockModules
    .filter((module) => !module.core && selected.has(module.key))
    .flatMap((module) => module.handlers)
}

export const handlers = mockModules.flatMap((module) => module.handlers)
