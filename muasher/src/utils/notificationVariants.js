const SEPARATOR = ' — '

export function notificationVariantLabel(variant, t, te) {
  if (!variant) return t('settings.notifications.texts.variants.single')
  return variant
    .split('.')
    .map((segment) => {
      const key = `settings.notifications.texts.variants.${segment}`
      return te(key) ? t(key) : segment
    })
    .join(SEPARATOR)
}
