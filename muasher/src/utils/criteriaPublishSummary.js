export function publishSummary(t, outcome) {
  const version = outcome?.data ?? {}
  const meta = outcome?.meta ?? {}
  const parts = [t('criteria.publish.success', { number: version.versionNumber })]
  if (meta.effect?.scope) {
    parts.push(t(`criteria.publish.effects.${meta.effect.scope}`))
    parts.push(
      t('criteria.publish.changeCount', { count: meta.changeCount ?? 0 }, meta.changeCount ?? 0),
    )
    parts.push(
      t(
        'criteria.publish.affectedOpen',
        { count: meta.affectedOpenCount ?? 0 },
        meta.affectedOpenCount ?? 0,
      ),
    )
  }
  return parts.join(' ')
}
