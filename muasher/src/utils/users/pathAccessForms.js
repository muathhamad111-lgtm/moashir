import { array as yupArray, object as yupObject, string as yupString } from 'yup'
import { REASON_LENGTH } from '@/constants/entityEnums'
import { REVIEWED_PATHS } from '@/constants/paths'
import { reasonText } from '@/utils/entitySchemas'

export const SCOPE_CHOICES = Object.freeze({ ALL: 'all', SELECTED: 'selected' })

function reasonField(t, required) {
  if (required) return reasonText(t)
  return yupString()
    .trim()
    .test(
      'min',
      t('validation.minChars', { min: REASON_LENGTH.MIN }),
      (value) => !value || value.length >= REASON_LENGTH.MIN,
    )
    .max(REASON_LENGTH.MAX, t('entities.validation.maxChars', { max: REASON_LENGTH.MAX }))
}

function withReason(values, payload) {
  const reason = (values.reason ?? '').trim()
  return reason ? { ...payload, reason } : payload
}

export function qualificationsSchema(t, reasonRequired) {
  return yupObject({
    qualifications: yupArray().of(yupString()),
    reason: reasonField(t, reasonRequired),
  })
}

export function qualificationsValuesOf(user) {
  return { qualifications: [...(user?.assessorQualifications ?? [])], reason: '' }
}

export function qualificationsPayload(values) {
  return withReason(values, { qualifications: [...values.qualifications] })
}

export function requestScopeSchema(t, reasonRequired) {
  return yupObject({
    scope: yupString().oneOf(Object.values(SCOPE_CHOICES)),
    paths: yupArray()
      .of(yupString())
      .when('scope', ([scope], rule) =>
        scope === SCOPE_CHOICES.SELECTED
          ? rule.min(1, t('users.pathAccess.validation.pathRequired'))
          : rule,
      ),
    reason: reasonField(t, reasonRequired),
  })
}

export function requestScopeValuesOf(user) {
  const scope = user?.requestScope
  const limited = scope && !scope.allPaths
  return {
    scope: limited ? SCOPE_CHOICES.SELECTED : SCOPE_CHOICES.ALL,
    paths: limited ? [...scope.paths] : [],
    reason: '',
  }
}

export function requestScopePayload(values) {
  if (values.scope === SCOPE_CHOICES.ALL) return withReason(values, { allPaths: true })
  const paths = REVIEWED_PATHS.filter((path) => values.paths.includes(path))
  return withReason(values, { allPaths: false, paths })
}
