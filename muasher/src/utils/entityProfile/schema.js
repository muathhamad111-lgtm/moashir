import { boolean as yupBoolean, object as yupObject, string as yupString } from 'yup'
import { entityProfileShape, registryShape, unifiedNumberField } from '@/utils/entitySchemas'
import { editableFieldsOf } from './formValues'

function rulesOf(t, today) {
  const profile = entityProfileShape(t)
  const registry = registryShape(t, today)
  return {
    'name.ar': profile.name.fields.ar,
    'name.en': profile.name.fields.en,
    unifiedNumber: unifiedNumberField(t, { required: false }),
    licenseNumber: registry.licenseNumber,
    foundedOn: registry.foundedOn,
    entityType: profile.entityType,
    developmentSector: profile.developmentSector,
    orgSize: profile.orgSize,
    regionId: profile.regionId,
    cityId: profile.cityId,
    governanceGrade: profile.governanceGrade,
    employeesCount: profile.employeesCount,
    annualBeneficiariesCount: profile.annualBeneficiariesCount,
    hasStrategicPlan: yupString(),
    authorizedPersonName: profile.authorizedPersonName,
    phone: profile.phone,
    websiteUrl: registry.websiteUrl,
    marketingConsent: yupBoolean(),
  }
}

function withRequirement(t, rule, required) {
  return required && rule.type === 'string' ? rule.required(t('validation.required')) : rule
}

function nest(shape) {
  const tree = {}
  Object.entries(shape).forEach(([path, rule]) => {
    const [head, leaf] = path.split('.')
    if (!leaf) tree[head] = rule
    else tree[head] = { ...tree[head], [leaf]: rule }
  })
  return Object.fromEntries(
    Object.entries(tree).map(([key, value]) => [
      key,
      value?.__isYupSchema__ ? value : yupObject(value),
    ]),
  )
}

export function profileSectionSchema(t, { fields, required, today }) {
  const rules = rulesOf(t, today)
  const shape = Object.fromEntries(
    editableFieldsOf(fields).map((name) => [
      name,
      withRequirement(t, rules[name], required.includes(name)),
    ]),
  )
  return yupObject(nest(shape))
}
