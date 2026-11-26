// The actor's roles as one readable line; a role without a label shows its key.
export function actorRolesText(roles, { t, te }) {
  return roles
    .map((role) => (te(`users.roleLabels.${role}`) ? t(`users.roleLabels.${role}`) : role))
    .join(t('common.listSeparator'))
}
