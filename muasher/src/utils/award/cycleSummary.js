import { CANDIDATE_ROLES, MEMBER_ROLES } from '@/constants/advanced'
import { lacksInitialAssessor } from '@/utils/award/cycleSetup'

function identity(cycle, format) {
  return [
    { label: format.t('cycles.form.nameAr'), value: format.plain(cycle.name?.ar) },
    { label: format.t('cycles.form.nameEn'), value: format.plain(cycle.name?.en) },
    { label: format.t('cycles.form.year'), value: format.plain(cycle.year) },
    {
      label: format.t('cycles.setup.identity.description'),
      value: format.text(cycle.description),
      long: true,
    },
  ]
}

function dates(cycle, format) {
  return [
    {
      label: format.t('cycles.dates.submission'),
      value: format.window(cycle.submissionStart, cycle.submissionEnd),
    },
    {
      label: format.t('cycles.dates.committee'),
      value: format.window(cycle.committeeStart, cycle.committeeEnd),
    },
    { label: format.t('cycles.dates.announcement'), value: format.date(cycle.announcementAt) },
  ]
}

function regionsRow(cycle, format) {
  const names = cycle.regions.map((region) => format.text(region.name))
  const count = format.t('cycles.setup.regions.count', {
    count: names.length,
    total: format.regionsTotal,
  })
  return {
    label: format.t('cycles.form.regions'),
    value: `${count}: ${format.list(names)}`,
    long: true,
  }
}

function conditions(cycle, format) {
  return [
    regionsRow(cycle, format),
    {
      label: format.t('cycles.form.minEntityAgeYears'),
      value: format.plain(cycle.conditions.minEntityAgeYears),
    },
    {
      key: 'threshold',
      label: format.t('cycles.form.disqualificationScore'),
      value: format.plain(cycle.conditions.disqualificationScore),
    },
    {
      label: format.t('cycles.form.participationCertificate'),
      value: format.flag(cycle.participationCertificateEnabled),
    },
  ]
}

function deadlines(cycle, format) {
  return [
    {
      label: format.t('cycles.form.initialReviewSlaDays'),
      value: format.plain(cycle.initialReviewSlaDays),
    },
  ]
}

function fee(cycle, format) {
  const amount = Number(cycle.entryFee ?? 0)
  return [
    {
      label: format.t('cycles.setup.fee.label'),
      value:
        amount > 0
          ? format.t('cycles.setup.fee.paidAmount', { amount: cycle.entryFee })
          : format.t('cycles.setup.fee.free'),
    },
  ]
}

function memberRow(cycle, role, format) {
  const names = cycle.members.filter((member) => member.role === role).map((member) => member.name)
  return { label: format.t(`cycles.staff.roles.${role}`), value: format.list(names) }
}

function committeeRow(committee, format) {
  return {
    key: 'committee',
    label: format.t('cycles.staff.committee.label'),
    value: committee.length ? format.list(committee) : format.t('cycles.staff.committee.none'),
  }
}

function team(cycle, format, committee) {
  const missing = lacksInitialAssessor(cycle)
  const assessors = cycle.initialAssessors.filter((link) => link.isActive).map((link) => link.name)
  return [
    ...Object.values(MEMBER_ROLES).map((role) => memberRow(cycle, role, format)),
    {
      label: format.t(`cycles.staff.roles.${CANDIDATE_ROLES.INITIAL_ASSESSOR}`),
      value: missing ? format.t('cycles.setup.review.missingAssessor') : format.list(assessors),
      missing,
    },
    ...(Array.isArray(committee) ? [committeeRow(committee, format)] : []),
  ]
}

const SECTIONS = { identity, dates, conditions, deadlines, fee, team }

export function summarySections(cycle, format, committee = null) {
  return Object.entries(SECTIONS)
    .filter(([key]) => key !== 'team' || cycle.revealsStaff)
    .map(([key, build]) => ({
      key,
      title: format.t(`cycles.setup.steps.${key}`),
      rows: build(cycle, format, committee),
    }))
}
