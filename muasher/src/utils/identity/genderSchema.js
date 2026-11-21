import { string } from 'yup'
import { GENDER_VALUES } from '@/constants/identityChanges'

export function genderRule(t) {
  return string()
    .required(t('nationalIdentity.gender.required'))
    .oneOf(GENDER_VALUES, t('nationalIdentity.gender.required'))
}
