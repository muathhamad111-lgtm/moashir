import { BAR_LABELS } from '@/constants/dashboardWidgets'
import { localizedText } from '@/utils/localizedText'

const DECADE_SPAN = 9

function pathLabel(item, { t, locale }) {
  return localizedText(item.pathLabel, locale) || t(`paths.labels.${item.path}`)
}

function decadeLabel(item, { t }) {
  const start = Number(item.key)
  if (!item.key || Number.isNaN(start)) return t('dashboard.bars.notReported')
  return t('dashboard.bars.decade', { from: start, to: start + DECADE_SPAN })
}

function keyedLabel(prefix) {
  return (item, { t, te }) => {
    const key = `${prefix}.${item.key}`
    return item.key && te(key) ? t(key) : t('dashboard.bars.notReported')
  }
}

const RESOLVERS = Object.freeze({
  [BAR_LABELS.NAME]: (item, { locale }) => localizedText(item.name, locale),
  [BAR_LABELS.PATH]: pathLabel,
  [BAR_LABELS.SECTOR]: keyedLabel('entities.enums.developmentSector'),
  [BAR_LABELS.DECADE]: decadeLabel,
  [BAR_LABELS.BAND]: keyedLabel('dashboard.bars.bands'),
  [BAR_LABELS.HISTOGRAM]: keyedLabel('dashboard.bars.histogram'),
  [BAR_LABELS.PURPOSE]: (item, { t, te }) =>
    te(`finance.purposes.${item.purpose}`) ? t(`finance.purposes.${item.purpose}`) : item.purpose,
})

export function barLabelOf(kind, item, i18n) {
  return (RESOLVERS[kind] ?? RESOLVERS[BAR_LABELS.NAME])(item, i18n)
}

export function barRows(definition, items, i18n) {
  const rows = (Array.isArray(items) ? items : []).map((item, index) => ({
    id: `${index}-${item.key ?? item.regionId ?? item.path ?? item.purpose ?? index}`,
    label: barLabelOf(definition.label, item, i18n),
    count: Number(item.count ?? 0),
    amount: definition.amountKey ? (item[definition.amountKey] ?? null) : null,
  }))
  const max = Math.max(1, ...rows.map((row) => row.count))
  return rows.map((row) => ({ ...row, ratio: Math.round((row.count / max) * 100) }))
}
