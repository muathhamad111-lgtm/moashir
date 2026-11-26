// The cycle view page (operator, 2026-11-24): the key facts in one card, then the details in compact cards.

function feeValue(cycle, format) {
  const amount = Number(cycle.entryFee ?? 0)
  return amount > 0
    ? format.t('cycles.setup.fee.paidAmount', { amount: cycle.entryFee })
    : format.t('cycles.setup.fee.free')
}

export function overviewFacts(cycle, format) {
  return [
    {
      key: 'submission',
      icon: 'calendar',
      label: format.t('cycles.dates.submission'),
      value: format.window(cycle.submissionStart, cycle.submissionEnd),
    },
    {
      key: 'committee',
      icon: 'users-two',
      label: format.t('cycles.dates.committee'),
      value: format.window(cycle.committeeStart, cycle.committeeEnd),
    },
    {
      key: 'announcement',
      icon: 'award',
      label: format.t('cycles.dates.announcement'),
      value: format.date(cycle.announcementAt),
    },
    {
      key: 'regions',
      icon: 'layers',
      label: format.t('cycles.form.regions'),
      value: format.t('cycles.setup.regions.count', {
        count: cycle.regions.length,
        total: format.regionsTotal,
      }),
    },
    {
      key: 'fee',
      icon: 'sar',
      label: format.t('cycles.setup.fee.label'),
      value: feeValue(cycle, format),
    },
    {
      key: 'threshold',
      icon: 'gauge',
      label: format.t('cycles.form.disqualificationScore'),
      value: format.plain(cycle.conditions.disqualificationScore),
    },
  ]
}

// Dates, the fee and the threshold already sit in the facts card; the rest is regrouped here.
export function detailGroups(sections, format) {
  const rowsOf = (key) => sections.find((section) => section.key === key)?.rows ?? []
  const identity = rowsOf('identity')
  const groups = [
    {
      key: 'identity',
      title: format.t('cycles.setup.steps.identity'),
      rows: identity.filter((row) => !row.long),
      text: identity.find((row) => row.long)?.value ?? '',
    },
    {
      key: 'conditions',
      title: format.t('cycles.detail.conditions'),
      rows: [
        ...rowsOf('conditions').filter((row) => row.key !== 'threshold'),
        ...rowsOf('deadlines'),
      ],
    },
  ]
  const team = rowsOf('team')
  if (team.length)
    groups.push({ key: 'team', title: format.t('cycles.setup.steps.team'), rows: team })
  return groups
}
