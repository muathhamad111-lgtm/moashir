import { defaultRoleMatrixFixture, permissionCatalogFixture, rolesFixture } from '../fixtures/permissions'

const catalog = permissionCatalogFixture.map((item) => ({
  ...item,
  label: { ...item.label },
  description: { ...item.description },
  lockedToRoles: [...item.lockedToRoles],
}))

const matrix = Object.fromEntries(
  Object.entries(defaultRoleMatrixFixture).map(([role, keys]) => [role, [...keys]]),
)

const NOT_EDITABLE_ROLES = new Set(['entity'])

export function listCatalog(module = null) {
  return catalog.filter((item) => !module || item.module === module)
}

export function findPermission(key) {
  return catalog.find((item) => item.key === key) ?? null
}

export function updatePermissionLabels(key, { label, description }) {
  const record = findPermission(key)
  if (!record) return null
  record.label = { ...label }
  if (description) record.description = { ...description }
  return record
}

export function listRoles() {
  return rolesFixture.map((role) => ({ ...role }))
}

export function findRole(key) {
  return rolesFixture.find((role) => role.key === key) ?? null
}

export function isRoleEditable(key) {
  return !NOT_EDITABLE_ROLES.has(key)
}

export function roleMatrix() {
  return Object.fromEntries(Object.entries(matrix).map(([role, keys]) => [role, [...keys]]))
}

export function setRolePermissions(role, keys) {
  matrix[role] = [...new Set(keys)]
  return [...matrix[role]]
}

export function unknownPermissionKeys(keys) {
  return keys.filter((key) => !findPermission(key))
}

export function lockedViolations(role, keys) {
  return keys.filter((key) => {
    const locked = findPermission(key)?.lockedToRoles ?? []
    return locked.length > 0 && !locked.includes(role)
  })
}

export function grantedPermissionKeys(roles = []) {
  const keys = new Set(roles.flatMap((role) => matrix[role] ?? []))
  return catalog.filter((item) => keys.has(item.key)).map((item) => item.key)
}

export function allPermissionKeys() {
  return catalog.map((item) => item.key)
}
