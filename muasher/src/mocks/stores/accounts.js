import { ROLES } from '@/constants/roles'
import { usersFixture } from '../fixtures/users'
import { IDLE_TIMEOUT_MINUTES, SESSION_ABSOLUTE_HOURS } from '../fixtures/auth'
import { COMMITTEE_CYCLE_ID } from '../fixtures/committee'
import {
  findEntity,
  findEntityByOwnerEmail,
  hasCompleteProfile,
  referenceOf,
} from './entitiesStore'
import { permissionsOf } from './sessionPermissions'
import { findRole } from './permissionsStore'
import { profileLocaleOf } from './preferencesStore'
import { DEFAULT_LOCALE } from '@/constants/locales'

const HOUR_MS = 60 * 60 * 1000
const DEFAULT_ENTITY_ID = 5001
const DEFAULT_COMMITTEE_CYCLE = COMMITTEE_CYCLE_ID

function sameEmail(left, right) {
  return (
    String(left ?? '')
      .trim()
      .toLowerCase() ===
    String(right ?? '')
      .trim()
      .toLowerCase()
  )
}

export function findAccountByEmail(email) {
  const entity = findEntityByOwnerEmail(email)
  if (entity) return { kind: 'entity', record: entity }
  const staff = usersFixture.find(
    (item) => sameEmail(item.email, email) && !item.roles.includes(ROLES.ENTITY),
  )
  return staff ? { kind: 'staff', record: staff } : null
}

export function descriptorFor(account) {
  const locale = profileLocaleOf(account.record.id)
  if (account.kind === 'entity') {
    return { id: account.record.id, roles: [ROLES.ENTITY], entityId: account.record.id, locale }
  }
  return {
    id: account.record.id,
    roles: [...account.record.roles],
    entityId: null,
    committeeCycles: [],
    locale,
  }
}

export const COMMITTEE_PERSONA = 'committee_assessor'

const COMMITTEE_PERSONA_DESCRIPTOR = {
  id: 'jur-9404',
  roles: [ROLES.ASSESSOR],
  entityId: null,
  committeeCycles: [DEFAULT_COMMITTEE_CYCLE],
}

function committeeAppointmentsOf(descriptor) {
  return (descriptor.committeeCycles ?? []).map((cycleId) => ({ cycleId, status: 'active' }))
}

export function descriptorForPersona(role) {
  if (role === COMMITTEE_PERSONA)
    return {
      ...COMMITTEE_PERSONA_DESCRIPTOR,
      locale: profileLocaleOf(COMMITTEE_PERSONA_DESCRIPTOR.id),
    }
  if (role === ROLES.ENTITY)
    return descriptorFor({ kind: 'entity', record: findEntity(DEFAULT_ENTITY_ID) })
  const staff = usersFixture.find((item) => item.roles.includes(role) && item.status === 'active')
  return staff
    ? descriptorFor({ kind: 'staff', record: staff })
    : {
        id: `persona-${role}`,
        roles: [role],
        entityId: null,
        locale: profileLocaleOf(`persona-${role}`),
      }
}

function entitySummary(entity) {
  return {
    id: entity.id,
    reference: referenceOf(entity),
    name: { ar: entity.name.ar, en: entity.name.en ?? null },
    status: entity.status,
    profileCompleted: hasCompleteProfile(entity),
  }
}

function personaName(roles) {
  return findRole(roles[0])?.label ?? { ar: '', en: '' }
}

export function sessionPayload(descriptor) {
  const entity = descriptor.entityId ? findEntity(descriptor.entityId) : null
  const staff = entity ? null : usersFixture.find((item) => item.id === descriptor.id)
  return {
    id: descriptor.id,
    name: descriptor.name ?? entity?.owner.name ?? staff?.name ?? personaName(descriptor.roles),
    email: entity?.owner.email ?? staff?.email ?? '',
    locale: descriptor.locale ?? DEFAULT_LOCALE,
    theme: null,
    roles: descriptor.roles.map((key) => ({
      key,
      label: findRole(key)?.label ?? { ar: key, en: key },
    })),
    permissions: permissionsOf({
      roles: descriptor.roles,
      superAdmin: descriptor.superAdmin === true,
    }),
    isSuperAdmin: descriptor.superAdmin === true,
    mfaEnabled: !entity,
    passwordChangeRequired: false,
    entityId: entity?.id ?? null,
    entity: entity ? entitySummary(entity) : null,
    committeeAppointments: committeeAppointmentsOf(descriptor),
    sessionExpiresAt: new Date(Date.now() + SESSION_ABSOLUTE_HOURS * HOUR_MS).toISOString(),
    idleTimeoutMinutes: entity ? IDLE_TIMEOUT_MINUTES.entity : IDLE_TIMEOUT_MINUTES.staff,
  }
}
