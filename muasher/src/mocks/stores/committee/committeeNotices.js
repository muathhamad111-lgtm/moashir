import { pushNotification } from '../notificationsStore'
import { committeeLabelOf } from './committeeEntity'

const ASSIGNMENTS_LINK = '/admin/committee/assignments'
const DISCUSSIONS_LINK = '/admin/committee/discussions'

function notify(code, recipientRole, title, body, link) {
  pushNotification({ code, recipientRole, title, body, link })
}

function secretaryLink(record) {
  return `/admin/award/cycles/${record.awardCycleId}/secretary/${record.id}`
}

export function announceDistributed(cycle, rows) {
  const title = {
    ar: 'وُزّعت طلبات على لجنة المقيّمين',
    en: 'Requests were distributed to the committee',
  }
  const body = {
    ar: `وُزّع ${rows.length} تقييمًا جديدًا على مقيّمي اللجنة في ${cycle.name.ar}.`,
    en: `${rows.length} new evaluations were distributed to the committee assessors of ${cycle.name.en}.`,
  }
  notify('ADV-09', 'assessor', title, body, ASSIGNMENTS_LINK)
}

export function announceAllSubmitted(record) {
  const title = {
    ar: 'أرسل جميع مقيّمي اللجنة تقييماتهم',
    en: 'Every committee assessor submitted',
  }
  const body = {
    ar: `حُسب متوسط اللجنة للطلب ${committeeLabelOf(record)} وهو بانتظار اعتماد أمين الجائزة.`,
    en: `The committee average of ${committeeLabelOf(record)} was computed and awaits the Award Secretary.`,
  }
  notify('ADV-12', 'award_secretary', title, body, secretaryLink(record))
}

export function announceReturnedToCommittee(record, note) {
  const title = {
    ar: 'أُعيد طلب إلى لجنة المقيّمين',
    en: 'A request was returned to the committee',
  }
  const body = {
    ar: `أعاد أمين الجائزة الطلب ${committeeLabelOf(record)} إلى اللجنة: ${note}`,
    en: `The Award Secretary returned ${committeeLabelOf(record)} to the committee: ${note}`,
  }
  notify('ADV-16', 'assessor', title, body, ASSIGNMENTS_LINK)
}

export function announceAverageApproved(record) {
  const title = { ar: 'اعتُمد متوسط اللجنة', en: 'Committee average approved' }
  const body = {
    ar: `اعتمد أمين الجائزة متوسط اللجنة للطلب ${record.number}.`,
    en: `The Award Secretary approved the committee average of ${record.number}.`,
  }
  notify('ADV-17', 'system_admin', title, body, secretaryLink(record))
}

export function announceDiscussionOpened(record) {
  const title = { ar: 'فُتح نقاش للجنة المقيّمين', en: 'A committee discussion was opened' }
  const body = {
    ar: `فتح أمين الجائزة نقاشًا حول الطلب ${committeeLabelOf(record)}.`,
    en: `The Award Secretary opened a discussion on ${committeeLabelOf(record)}.`,
  }
  notify('ADV-29', 'assessor', title, body, DISCUSSIONS_LINK)
}

export function announceResults(cycle) {
  const title = { ar: 'أُعلنت نتائج الجائزة', en: 'Award results announced' }
  const body = {
    ar: `أُعلنت نتائج ${cycle.name.ar} لعام ${cycle.year}.`,
    en: `The results of ${cycle.name.en} ${cycle.year} were announced.`,
  }
  notify('ADV-18', 'entity', title, body, '/assessment/advanced')
  notify('ADV-18', 'system_admin', title, body, `/admin/award/cycles/${cycle.id}/results`)
}
