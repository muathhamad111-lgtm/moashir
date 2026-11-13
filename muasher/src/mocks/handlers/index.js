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
import { pricesSettingsHandlers } from './pricesSettings'
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

export const handlers = [
  ...sanctumHandlers,
  ...(import.meta.env.DEV ? devSessionHandlers() : []),
  ...kpisHandlers,
  ...notificationsHandlers,
  ...criteriaHandlers,
  ...legalHandlers,
  ...legalVersionsHandlers,
  ...contentHandlers,
  ...certificateLevelsHandlers,
  ...pathGuidesHandlers,
  ...contactInboxHandlers,
  ...contactSettingsHandlers,
  ...contactMessagesHandlers,
  ...aiHandlers,
  ...knowledgeBaseHandlers,
  ...usersHandlers,
  ...authHandlers,
  ...entityEmailChangesHandlers,
  ...entityLifecycleHandlers,
  ...entityProfileChangesHandlers,
  ...entitiesHandlers,
  ...privacyRequestsHandlers,
  ...regionsHandlers,
  ...awardHandlers,
  ...assessmentsHandlers,
  ...integrationsHandlers,
  ...paymentIntegrationHandlers,
  ...smsIntegrationHandlers,
  ...emailIntegrationHandlers,
  ...aiIntegrationHandlers,
  ...platformSettingsHandlers,
  ...notificationTypesHandlers,
  ...paymentsHandlers,
  ...moyasarSandboxHandlers,
  ...bankTransfersHandlers,
  ...paymentChannelsHandlers,
  ...financeHandlers,
  ...financeSettingsHandlers,
  ...pricesSettingsHandlers,
  ...servicePricesHandlers,
  ...messagingHandlers,
  ...accreditedHandlers,
  ...requestsHandlers,
  ...assessorReviewHandlers,
  ...assessmentSettingsHandlers,
  ...insightsHandlers,
  ...certificatesStaffHandlers,
  ...certificatesHandlers,
  ...certificateSettingsHandlers,
  ...bagHandlers,
  ...bagRequestsHandlers,
  ...awardResultsHandlers,
  ...committeeHandlers,
  ...secretaryHandlers,
  ...permissionsHandlers,
  ...auditLogsHandlers,
  ...entityBoardHandlers,
  ...dashboardHandlers,
  ...brandingHandlers,
  ...profileHandlers,
  ...searchHandlers,
]
