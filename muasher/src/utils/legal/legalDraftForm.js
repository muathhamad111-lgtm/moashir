import { object, string } from 'yup'

export const LEGAL_DRAFT_LIMITS = Object.freeze({
  versionPattern: /^[A-Za-z0-9._-]{1,20}$/,
  titleMax: 200,
  bodyMax: 60000,
})

const LOCALES = ['ar', 'en']

function titleField(t) {
  return string()
    .trim()
    .required(t('validation.required'))
    .max(
      LEGAL_DRAFT_LIMITS.titleMax,
      t('legalAdmin.validation.titleMax', { max: LEGAL_DRAFT_LIMITS.titleMax }),
    )
    .test('single-line', t('legalAdmin.validation.singleLine'), (value) => !/\n/.test(value ?? ''))
}

function bodyField(t) {
  return string()
    .trim()
    .required(t('validation.required'))
    .max(
      LEGAL_DRAFT_LIMITS.bodyMax,
      t('legalAdmin.validation.bodyMax', { max: LEGAL_DRAFT_LIMITS.bodyMax }),
    )
}

export function legalDraftSchema(t) {
  return object({
    version: string()
      .trim()
      .required(t('validation.required'))
      .matches(LEGAL_DRAFT_LIMITS.versionPattern, t('legalAdmin.validation.version')),
    title: object(Object.fromEntries(LOCALES.map((locale) => [locale, titleField(t)]))),
    body: object(Object.fromEntries(LOCALES.map((locale) => [locale, bodyField(t)]))),
  })
}

export function legalDraftValues(version) {
  return {
    version: version?.version ?? '',
    title: { ar: version?.title?.ar ?? '', en: version?.title?.en ?? '' },
    body: { ar: version?.body?.ar ?? '', en: version?.body?.en ?? '' },
  }
}

export function legalDraftPayload(values) {
  return {
    version: values.version.trim(),
    title: { ar: values.title.ar.trim(), en: values.title.en.trim() },
    body: { ar: values.body.ar, en: values.body.en },
  }
}

export function changedLegalDraft(saved, values) {
  const next = legalDraftPayload(values)
  const changes = {}
  if (next.version !== saved.version) changes.version = next.version
  if (LOCALES.some((locale) => next.title[locale] !== saved.title[locale]))
    changes.title = next.title
  if (LOCALES.some((locale) => next.body[locale] !== (saved.body?.[locale] ?? '')))
    changes.body = next.body
  return changes
}

export function suggestedVersionLabel(now = new Date()) {
  const month = String(now.getUTCMonth() + 1).padStart(2, '0')
  return `${now.getUTCFullYear()}-${month}`
}
