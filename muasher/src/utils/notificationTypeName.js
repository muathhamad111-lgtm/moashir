export function notificationTypeName(code, t, te) {
  const key = `notifications.typeNames.${code}`
  return te(key) ? t(key) : code
}
