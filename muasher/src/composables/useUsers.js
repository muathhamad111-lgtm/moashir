import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ROLES } from '@/constants/roles'

export const ROLE_ORDER = [
  ROLES.SYSTEM_ADMIN,
  ROLES.CRITERIA_OFFICER,
  ROLES.REQUEST_FOLLOWER,
  ROLES.ASSESSOR,
  ROLES.FILTERING_OFFICER,
  ROLES.AWARD_SECRETARY,
  ROLES.CONTENT_OFFICER,
  ROLES.FINANCE_OFFICER,
  ROLES.ENTITY,
]

const ROLE_CAPABILITY_KEYS = {
  [ROLES.SYSTEM_ADMIN]: ['administration'],
  [ROLES.CRITERIA_OFFICER]: ['criteria'],
  [ROLES.ENTITY]: ['ownAssessments', 'ownProfile'],
  [ROLES.REQUEST_FOLLOWER]: ['requestsQueue'],
  [ROLES.ASSESSOR]: ['assessorScoring', 'kitAnalysis'],
  [ROLES.FILTERING_OFFICER]: ['filtering'],
  [ROLES.AWARD_SECRETARY]: ['secretaryApproval'],
  [ROLES.CONTENT_OFFICER]: ['kpisContent', 'knowledgeBase'],
  [ROLES.FINANCE_OFFICER]: ['finance'],
}

export function useUsers() {
  const { t } = useI18n()

  function roleLabel(role) {
    return role ? t(`users.roleLabels.${role}`) : ''
  }

  const roleOptions = computed(() =>
    ROLE_ORDER.map((role) => ({ value: role, label: roleLabel(role) })),
  )

  const roleFilterOptions = computed(() => [
    { value: '', label: t('users.filters.allRoles') },
    ...roleOptions.value,
  ])

  const statusFilterOptions = computed(() => [
    { value: '', label: t('users.filters.allStatuses') },
    { value: 'active', label: t('status.active') },
    { value: 'inactive', label: t('status.inactive') },
  ])

  function roleCapabilities(role) {
    const keys = ROLE_CAPABILITY_KEYS[role] ?? []
    return keys.map((key) => t(`users.capabilities.${key}`))
  }

  return {
    roleLabel,
    roleOptions,
    roleFilterOptions,
    statusFilterOptions,
    roleCapabilities,
  }
}
