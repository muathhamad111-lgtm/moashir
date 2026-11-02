import { pushNotification } from '../notificationsStore'

const ENTITY_LINK = '/assessment/advanced'

function requestLink(record) {
  return `/admin/requests/${record.id}`
}

function notify(code, recipientRole, title, body, link) {
  pushNotification({ code, recipientRole, title, body, link })
}

export function announceCycleOpened(cycle) {
  const title = { ar: 'فُتح التقديم في دورة الجائزة', en: 'Award cycle submissions opened' }
  const body = { ar: `فُتح التقديم في ${cycle.name.ar} لعام ${cycle.year}.`, en: `Submissions opened for ${cycle.name.en} ${cycle.year}.` }
  notify('ADV-01', 'entity', title, body, ENTITY_LINK)
  notify('ADV-01', 'system_admin', title, body, `/admin/award/cycles/${cycle.id}`)
  notify('ADV-01', 'request_follower', title, body, '/admin/requests')
}

export function announceConditionsRejected(record) {
  const title = { ar: 'لم تستوفِ جهتكم شروط الدخول', en: 'Entry conditions not met' }
  const body = {
    ar: `سُجّل الطلب ${record.number} لعدم استيفاء شروط الدخول في دورة الجائزة.`,
    en: `Request ${record.number} was recorded as not meeting the entry conditions of the award cycle.`,
  }
  notify('ADV-03', 'entity', title, body, ENTITY_LINK)
}

export function announceSubmitted(record, { resubmission }) {
  const title = resubmission ? { ar: 'أُعيد إرسال طلب الجائزة', en: 'Award request resubmitted' } : { ar: 'أُرسل طلب الجائزة', en: 'Award request submitted' }
  const body = resubmission
    ? { ar: `أعادت الجهة إرسال الطلب ${record.number} بعد الاستكمال.`, en: `The entity resubmitted request ${record.number} after completion.` }
    : { ar: `استُلم الطلب ${record.number} وهو بانتظار الإسناد.`, en: `Request ${record.number} was received and awaits assignment.` }
  if (!resubmission) notify('ADV-04', 'entity', title, body, ENTITY_LINK)
  notify('ADV-04', 'request_follower', title, body, requestLink(record))
}

export function announceReturned(record, completion) {
  const title = { ar: 'أُعيد طلب الجائزة للاستكمال', en: 'Award request returned for completion' }
  const body = {
    ar: `أُعيد الطلب ${record.number} للاستكمال. المهلة تنتهي في ${completion.deadlineAt.slice(0, 10)}.`,
    en: `Request ${record.number} was returned for completion. The deadline is ${completion.deadlineAt.slice(0, 10)}.`,
  }
  notify('ADV-25', 'entity', title, body, ENTITY_LINK)
}

export function announceAdvancedExpiry(record, outcome) {
  const title = { ar: 'انتهت مهلة الاستكمال', en: 'Completion deadline passed' }
  const body =
    outcome === 'decline'
      ? { ar: `انتهت مهلة استكمال الطلب ${record.number}، فأُغلق لعدم الاستكمال.`, en: `The completion deadline of request ${record.number} passed, so it was closed as incomplete.` }
      : { ar: `انتهت مهلة استكمال الطلب ${record.number}، ويمضي الطلب بالبيانات المتاحة.`, en: `The completion deadline of request ${record.number} passed. The request continues with the data available.` }
  notify('ADV-27', 'entity', title, body, ENTITY_LINK)
  notify('ADV-27', 'request_follower', title, body, requestLink(record))
}

export function announceWithdrawn(record) {
  const title = { ar: 'سُحب طلب الجائزة', en: 'Award request withdrawn' }
  const body = { ar: `سُحب الطلب ${record.number} بناءً على طلب الجهة.`, en: `Request ${record.number} was withdrawn at the entity's request.` }
  notify('ADV-31', 'entity', title, body, ENTITY_LINK)
  notify('ADV-31', 'request_follower', title, body, requestLink(record))
  notify('ADV-31', 'assessor', title, body, '/admin/requests/assigned')
}

export function announceInitialCompleted(record) {
  const title = { ar: 'اكتمل التقييم الأولي', en: 'Initial assessment completed' }
  const body = { ar: `اكتمل التقييم الأولي للطلب ${record.number} وهو بانتظار التصفية.`, en: `The initial assessment of request ${record.number} is complete and awaits filtering.` }
  notify('ADV-06', 'filtering_officer', title, body, `/admin/award/cycles/${record.awardCycleId}/filtering/${record.id}`)
  notify('ADV-06', 'request_follower', title, body, requestLink(record))
}

export function announceFilteringDecision(record, decision) {
  const qualified = decision.outcome === 'qualified'
  const title = qualified ? { ar: 'تأهّل طلب لتقييم اللجنة', en: 'Request qualified for the committee assessment' } : { ar: 'لم يتأهل طلب', en: 'Request not qualified' }
  const body = qualified
    ? { ar: `تأهّل الطلب ${record.number} لمرحلة تقييم اللجنة.`, en: `Request ${record.number} qualified for the committee assessment.` }
    : { ar: `لم يتأهل الطلب ${record.number}، وأُغلق.`, en: `Request ${record.number} did not qualify and was closed.` }
  notify(qualified ? 'ADV-07' : 'ADV-08', 'filtering_officer', title, body, `/admin/award/cycles/${record.awardCycleId}/filtering/${record.id}`)
  if (decision.isOverride) {
    const alert = { ar: 'تأهيل استثنائي دون درجة الاستبعاد', en: 'Qualified below the disqualification score' }
    const detail = { ar: `أُهّل الطلب ${record.number} استثناءً دون درجة الاستبعاد مع ذكر السبب.`, en: `Request ${record.number} was qualified below the disqualification score with a recorded reason.` }
    notify('ADV-28', 'award_secretary', alert, detail, `/admin/award/cycles/${record.awardCycleId}/filtering/${record.id}`)
    notify('ADV-28', 'system_admin', alert, detail, `/admin/award/cycles/${record.awardCycleId}/filtering/${record.id}`)
  }
}

export function announceSubmissionsClosed(cycle) {
  const title = { ar: 'أُغلق التقديم في دورة الجائزة', en: 'Award cycle submissions closed' }
  const body = { ar: `أُغلق التقديم في ${cycle.name.ar} لعام ${cycle.year}.`, en: `Submissions closed for ${cycle.name.en} ${cycle.year}.` }
  ;['system_admin', 'request_follower', 'award_secretary'].forEach((role) => notify('CYC-01', role, title, body, `/admin/award/cycles/${cycle.id}`))
}

export function announceCycleClosed(cycle) {
  const title = { ar: 'أُغلقت دورة الجائزة', en: 'Award cycle closed' }
  const body = { ar: `أُغلقت ${cycle.name.ar} لعام ${cycle.year}.`, en: `${cycle.name.en} ${cycle.year} was closed.` }
  ;['system_admin', 'award_secretary'].forEach((role) => notify('CYC-02', role, title, body, `/admin/award/cycles/${cycle.id}`))
}

export function announceInitialAssessorAssigned(record) {
  const title = { ar: 'أُسند إليك طلب جائزة', en: 'An award request was assigned to you' }
  const body = { ar: `أُسند إليك الطلب ${record.number} للتقييم الأولي.`, en: `Request ${record.number} was assigned to you for the initial assessment.` }
  notify('ADV-05', 'assessor', title, body, `/admin/requests/${record.id}/review`)
}
