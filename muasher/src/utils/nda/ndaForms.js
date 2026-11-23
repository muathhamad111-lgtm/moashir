import { boolean as yupBoolean, object as yupObject, string as yupString } from 'yup'
import { reasonText } from '@/utils/entitySchemas'

export const NDA_REASON_MIN = 10
const SUMMARY_MAX = 2000

function summaryText(t) {
  return yupString()
    .trim()
    .required(t('validation.required'))
    .max(SUMMARY_MAX, t('validation.maxChars', { max: SUMMARY_MAX }))
}

export function ndaPublishSchema(t) {
  return yupObject({
    changeSummary: yupObject({ ar: summaryText(t), en: summaryText(t) }),
    reason: reasonText(t, NDA_REASON_MIN),
    confirmIrreversible: yupBoolean().oneOf([true], t('nda.admin.publish.confirmRequired')),
  })
}

export function ndaPublishPayload(values) {
  return {
    changeSummary: { ar: values.changeSummary.ar.trim(), en: values.changeSummary.en.trim() },
    reason: values.reason.trim(),
    confirmIrreversible: values.confirmIrreversible === true,
  }
}

// The role a user is asked to accept the agreement for, when a role being added is in its scope and the user
// has no acceptance of the current version.
export function ndaRoleWarningApplies({ scopeRoles = [], addedRoles = [], status = null }) {
  if (status === 'accepted') return false
  return addedRoles.some((role) => scopeRoles.includes(role))
}
