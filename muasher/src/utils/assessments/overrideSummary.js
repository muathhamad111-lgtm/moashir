// The live line of the override mode (design §B.3.5): «4 بنود معدلة · الإجمالي 57% → 58.4% · المستوى:
// فضي (بلا تغيير)». Without a preview yet, only the count is known.
export function overrideSummaryParts(t, { count, preview, formatPercent }) {
  const parts = [t('review.override.mode.changed', { count }, count)]
  if (!preview) return parts
  parts.push(
    t('review.override.mode.total', {
      before: formatPercent(preview.before.percent),
      after: formatPercent(preview.after.percent),
    }),
  )
  const levelName = (level) => t(`level.names.${level ?? 'none'}`)
  parts.push(
    preview.before.level === preview.after.level
      ? t('review.override.mode.levelSame', { level: levelName(preview.after.level) })
      : t('review.override.mode.levelChanged', {
          before: levelName(preview.before.level),
          after: levelName(preview.after.level),
        }),
  )
  return parts
}
