import { array as yupArray, object as yupObject, string as yupString } from 'yup'
import { nationalIdRule } from '@/utils/identity/nationalIdSchema'
import { normalizeNationalId } from '@/utils/identity/nationalId'
import { genderRule } from '@/utils/identity/genderSchema'

const NAME_MIN = 3

// An administrator enters the national ID only while none is recorded; it is saved directly and audited.
export function acceptsNationalId(user) {
  return !user?.nationalIdMasked
}

export function userFormSchema(t, { user }) {
  return yupObject({
    name: yupString()
      .required(t('validation.required'))
      .min(NAME_MIN, t('validation.minChars', { min: NAME_MIN })),
    email: yupString().required(t('validation.required')).email(t('validation.email')),
    roles: yupArray().of(yupString()).min(1, t('users.validation.roleRequired')),
    gender: genderRule(t),
    ...(acceptsNationalId(user) ? { nationalId: nationalIdRule(t, { required: false }) } : {}),
  })
}

export function userFormValues(user) {
  return {
    name: user?.name ?? '',
    email: user?.email ?? '',
    roles: Array.isArray(user?.roles) ? [...user.roles] : [],
    gender: user?.gender ?? '',
    nationalId: '',
  }
}

export function userFormPayload(values) {
  return {
    name: values.name.trim(),
    email: values.email.trim(),
    roles: values.roles,
    gender: values.gender,
    ...(values.nationalId ? { nationalId: normalizeNationalId(values.nationalId) } : {}),
  }
}
