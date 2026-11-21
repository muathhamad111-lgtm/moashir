import { string } from 'yup'
import { nationalIdProblem, normalizeNationalId } from './nationalId'

export function nationalIdRule(t, { required = true } = {}) {
  const base = string()
    .transform((value) => normalizeNationalId(value))
    .test('national-id', (value, context) => {
      if (!value) return true
      const problem = nationalIdProblem(value)
      return problem
        ? context.createError({ message: t(`nationalIdentity.field.rules.${problem}`) })
        : true
    })
  return required ? base.required(t('nationalIdentity.field.required')) : base
}
