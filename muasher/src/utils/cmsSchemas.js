import { mixed as yupMixed, object as yupObject, string as yupString } from 'yup'
import {
  CMS_BACKGROUND_PATTERN,
  CMS_LIMITS,
  CMS_LOCALIZED_LIMITS,
  CMS_SECTION_KEY_PATTERN,
  CMS_SECTION_TYPES,
} from '@/constants/cms'
import { PICKABLE_ICON_NAMES } from './icons/iconRegistry'
import { isSafeLink } from './safeLink'

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

function contentShape(t) {
  return {
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

export function contentSchema(t) {
  return yupObject(contentShape(t))
}

export function newSectionSchema(t) {
  return yupObject({
    ...contentShape(t),
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
