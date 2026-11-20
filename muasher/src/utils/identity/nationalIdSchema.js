import { string } from 'yup'
import { isValidNationalId, normalizeNationalId } from './nationalId'

export function nationalIdRule(t, { required = true } = {}) {
  const base = string()
    .transform((value) => normalizeNationalId(value))
    .test('national-id', t('nationalIdentity.field.invalid'), (value) =>
      value ? isValidNationalId(value) : !required,
    )
  return required ? base.required(t('nationalIdentity.field.required')) : base
}
