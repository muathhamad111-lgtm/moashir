function panelValue(key, icon) {
  return Object.freeze({
    key,
    icon,
    titleKey: `auth.panel.values.${key}.title`,
    bodyKey: `auth.panel.values.${key}.body`,
  })
}

export const AUTH_PANEL_VALUES = Object.freeze([
  panelValue('standards', 'list-check'),
  panelValue('review', 'user-check'),
  panelValue('improvement', 'chart-line'),
])
