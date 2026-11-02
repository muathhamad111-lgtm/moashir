import { ROLES } from '@/constants/roles'
import { criteriaNoticeTexts, criteriaScopeLabels } from '../fixtures/criteriaMessages'
import { pushNotification } from './notificationsStore'
import { interpolate } from './criteriaRejection'

const SETTINGS_LINK = '/admin/settings/criteria'
const OFFICER_AND_ADMIN = [ROLES.CRITERIA_OFFICER, ROLES.SYSTEM_ADMIN]

const RECIPIENTS = {
  'STD-01': OFFICER_AND_ADMIN,
  'STD-02': OFFICER_AND_ADMIN,
  'STD-03': [ROLES.CRITERIA_OFFICER],
  'STD-04': OFFICER_AND_ADMIN,
  'STD-05': OFFICER_AND_ADMIN,
  'STD-07': OFFICER_AND_ADMIN,
  'STD-08': OFFICER_AND_ADMIN,
}

const ALERTS = new Set(['STD-07', 'STD-08'])

export function criteriaLink(main = null, sub = null) {
  if (main && sub) return `${SETTINGS_LINK}/${main.id}/subs/${sub.id}`
  if (main) return `${SETTINGS_LINK}/${main.id}`
  return SETTINGS_LINK
}

function nameParams(node) {
  return { name_ar: node.name.ar, name_en: node.name.en }
}

function scopeParams(scope) {
  const label = criteriaScopeLabels[scope] ?? criteriaScopeLabels.all
  return { scope_ar: label.ar, scope_en: label.en }
}

export function notifyCriteria(code, { actorId, link = SETTINGS_LINK, params = {} }) {
  const texts = criteriaNoticeTexts[code]
  const body = { ar: interpolate(texts.body.ar, params), en: interpolate(texts.body.en, params) }
  return RECIPIENTS[code].map((recipientRole) =>
    pushNotification({
      code,
      type: ALERTS.has(code) ? 'alert' : 'info',
      recipientRole,
      actorId,
      title: { ...texts.title },
      body,
      link,
    }),
  )
}

export const criteriaNotices = {
  mainCreated: (actorId, main) =>
    notifyCriteria('STD-01', { actorId, link: criteriaLink(main), params: nameParams(main) }),
  mainWeightChanged: (actorId, main, previous, weight) =>
    notifyCriteria('STD-02', {
      actorId,
      link: criteriaLink(main),
      params: { ...nameParams(main), previous, weight },
    }),
  subCreated: (actorId, main, sub) =>
    notifyCriteria('STD-03', { actorId, link: criteriaLink(main, sub), params: nameParams(sub) }),
  evaluationSwitched: (actorId, main, sub, toGuidingPoints) =>
    notifyCriteria(toGuidingPoints ? 'STD-04' : 'STD-05', {
      actorId,
      link: criteriaLink(main, sub),
      params: nameParams(sub),
    }),
  becameInconsistent: (actorId, version, count) =>
    notifyCriteria('STD-07', { actorId, params: { version: version.versionNumber, count } }),
  publishedEdited: (actorId, count, scope) =>
    notifyCriteria('STD-08', { actorId, params: { count, ...scopeParams(scope) } }),
}
