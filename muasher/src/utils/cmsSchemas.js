import { array as yupArray, mixed as yupMixed, object as yupObject, string as yupString } from 'yup'
import {
  CMS_BACKGROUND_PATTERN,
  CMS_LIMITS,
  CMS_LOCALIZED_LIMITS,
  CMS_SECTION_KEY_PATTERN,
  CMS_SECTION_TYPES,
} from '@/constants/cms'
import { PICKABLE_ICON_NAMES } from './icons/iconRegistry'
import { contentImageId } from './contentImage'
import { isHttpsLink, isSafeLink } from './safeLink'

export function localizedSchema(t, max, { required = false } = {}) {
  const text = yupString().max(max, t('cms.validation.maxChars', { max }))
  const shape = yupObject({ ar: text, en: text })
  if (!required) return shape
  return shape.test('one-language', t('cms.validation.oneLanguage'), (value) =>
    Boolean(value?.ar?.trim() || value?.en?.trim()),
  )
}

function optionalPattern(t, pattern, messageKey) {
  return yupString().test(
    'pattern',
    t(messageKey),
    (value) => !value?.trim() || pattern.test(value.trim()),
  )
}

export function linkSchema(t) {
  return yupString().test(
    'safe-link',
    t('cms.validation.link'),
    (value) => !value?.trim() || isSafeLink(value.trim()),
  )
}

export function iconSchema(t) {
  return yupString().test(
    'icon',
    t('cms.validation.icon'),
    (value) => !value || PICKABLE_ICON_NAMES.includes(value),
  )
}

function pairedLanguage(t, max, other) {
  return yupString()
    .max(max, t('cms.validation.maxChars', { max }))
    .test('both-languages', t('cms.validation.bothLanguages'), function (value) {
      return Boolean(value?.trim()) || !this.parent?.[other]?.trim()
    })
}

function bothLanguages(t, max) {
  return yupObject({ ar: pairedLanguage(t, max, 'en'), en: pairedLanguage(t, max, 'ar') })
}

function metaFieldSchema(t, field) {
  if (field.kind === 'localized') return bothLanguages(t, field.max)
  if (field.kind === 'choices') {
    return yupArray().of(yupString().oneOf([...field.options], t('validation.required')))
  }
  if (field.kind === 'integer') {
    return yupString().test(
      'range',
      t('cms.validation.range', { min: field.min, max: field.max }),
      (value) => {
        if (value === '' || value === null || value === undefined) return true
        const number = Number(value)
        return Number.isInteger(number) && number >= field.min && number <= field.max
      },
    )
  }
  return yupString().test(
    'choice',
    t('validation.required'),
    (value) => !value || field.options.includes(value),
  )
}

export function metaFieldsSchema(t, fields = []) {
  return yupObject(
    Object.fromEntries(fields.map((field) => [field.key, metaFieldSchema(t, field)])),
  )
}

function contentShape(t, metaFields = []) {
  return {
    metaFields: metaFieldsSchema(t, metaFields),
    title: localizedSchema(t, CMS_LOCALIZED_LIMITS.title),
    subtitle: localizedSchema(t, CMS_LOCALIZED_LIMITS.subtitle),
    description: localizedSchema(t, CMS_LOCALIZED_LIMITS.description),
    ctaLabel: localizedSchema(t, CMS_LOCALIZED_LIMITS.ctaLabel),
    ctaUrl: linkSchema(t).max(
      CMS_LIMITS.ctaUrlMax,
      t('cms.validation.maxChars', { max: CMS_LIMITS.ctaUrlMax }),
    ),
    icon: iconSchema(t),
    background: optionalPattern(t, CMS_BACKGROUND_PATTERN, 'cms.validation.background'),
    image: yupMixed().nullable(),
  }
}

export function contentSchema(t, metaFields = []) {
  return yupObject(contentShape(t, metaFields))
}

export function newSectionSchema(t, metaFields = []) {
  return yupObject({
    ...contentShape(t, metaFields),
    key: yupString()
      .trim()
      .required(t('validation.required'))
      .max(
        CMS_LIMITS.sectionKeyMax,
        t('cms.validation.maxChars', { max: CMS_LIMITS.sectionKeyMax }),
      )
      .matches(CMS_SECTION_KEY_PATTERN, t('cms.validation.key')),
    type: yupString()
      .required(t('validation.required'))
      .oneOf([...CMS_SECTION_TYPES], t('validation.required')),
  })
}

export function pageDetailsSchema(t) {
  return yupObject({
    title: localizedSchema(t, CMS_LIMITS.pageTitleMax),
    seo: yupObject({
      title: localizedSchema(t, CMS_LIMITS.seoTitleMax),
      description: localizedSchema(t, CMS_LIMITS.seoDescriptionMax),
    }),
  })
}

function requiredText(t, max) {
  return yupString()
    .trim()
    .required(t('validation.required'))
    .max(max, t('cms.validation.maxChars', { max }))
}

export function partnerSchema(t) {
  return yupObject({
    title: yupObject({
      ar: requiredText(t, CMS_LOCALIZED_LIMITS.title),
      en: requiredText(t, CMS_LOCALIZED_LIMITS.title),
    }),
    ctaUrl: yupString()
      .max(CMS_LIMITS.ctaUrlMax, t('cms.validation.maxChars', { max: CMS_LIMITS.ctaUrlMax }))
      .test(
        'https',
        t('cms.partners.linkInvalid'),
        (value) => !value?.trim() || isHttpsLink(value.trim()),
      ),
    image: yupMixed()
      .nullable()
      .test('logo', t('cms.partners.logoRequired'), (value) => Boolean(contentImageId(value))),
  })
}
