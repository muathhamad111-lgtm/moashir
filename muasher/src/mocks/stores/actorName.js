export function actorDisplayName(user, fallback = null) {
  if (typeof user?.name === 'string') return user.name
  return user?.name?.ar ?? user?.name?.en ?? fallback
}
