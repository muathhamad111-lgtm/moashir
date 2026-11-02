export function rolesHolding(matrix, key) {
  return Object.entries(matrix)
    .filter(([, keys]) => keys.includes(key))
    .map(([role]) => role)
}

export function matrixChanges(matrix, key, selectedRoles, editableRoles) {
  return editableRoles
    .map((role) => {
      const current = matrix[role] ?? []
      const holds = current.includes(key)
      const shouldHold = selectedRoles.includes(role)
      if (holds === shouldHold) return null
      const keys = shouldHold ? [...current, key] : current.filter((item) => item !== key)
      return { role, keys }
    })
    .filter(Boolean)
}

export function labelsChanged(permission, { label, description }) {
  const same = (left = {}, right = {}) => left.ar === right.ar && left.en === right.en
  return !same(permission.label, label) || !same(permission.description, description)
}
