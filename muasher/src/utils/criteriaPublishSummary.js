export function publishSummary(t, outcome) {
  const version = outcome?.data ?? {}
  const meta = outcome?.meta ?? {}
  const affected = meta.affectedOpenCount ?? 0
  return {
    title: t('criteria.publish.success', { number: version.versionNumber }),
    message: meta.effect?.scope ? t('criteria.publish.affectedOpen', { count: affected }, affected) : '',
  }
}
