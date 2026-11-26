import { object, string } from 'yup'
import { nationalIdRule } from './nationalIdSchema'
import { normalizeNationalId } from './nationalId'

export const IDENTITY_CHANGE_RULES = Object.freeze({
  NAME_MIN: 2,
  NAME_MAX: 150,
  REASON_MIN: 10,
  REASON_MAX: 500,
})

const R = IDENTITY_CHANGE_RULES

export function identityChangeSchema(t, { withNationalId = true } = {}) {
  return object({
    newName: string()
      .trim()
      .test(
        'name-length',
        t('nationalIdentity.change.nameLength', { min: R.NAME_MIN, max: R.NAME_MAX }),
        (value) => (value ? value.length >= R.NAME_MIN && value.length <= R.NAME_MAX : true),
      )
      .test('something', t('nationalIdentity.change.nothing'), function hasChange(value) {
        return Boolean(value) || Boolean(this.parent.newNationalId)
      }),
    newNationalId: withNationalId ? nationalIdRule(t, { required: false }) : string(),
    reason: string()
      .trim()
      .required(t('validation.required'))
      .min(
        R.REASON_MIN,
        t('nationalIdentity.change.reasonLength', { min: R.REASON_MIN, max: R.REASON_MAX }),
      )
      .max(
        R.REASON_MAX,
        t('nationalIdentity.change.reasonLength', { min: R.REASON_MIN, max: R.REASON_MAX }),
      ),
    currentPassword: string().test(
      'password-for-national-id',
      t('nationalIdentity.change.passwordRequired'),
      function passwordWhenIdChanges(value) {
        return !this.parent.newNationalId || Boolean(value)
      },
    ),
  })
}

export function identityChangePayload(values) {
  const payload = { reason: values.reason.trim() }
  if (values.newName?.trim()) payload.newName = values.newName.trim()
  if (values.newNationalId) {
    payload.newNationalId = normalizeNationalId(values.newNationalId)
    payload.currentPassword = values.currentPassword
  }
  return payload
}
