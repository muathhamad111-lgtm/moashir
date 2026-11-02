import { HttpResponse } from 'msw'
import { interpolate, requestLocale } from './locale'

export class MockRejection extends Error {
  constructor(status, code, { fields = {}, meta = {}, params = {}, headers = {} } = {}) {
    super(code)
    this.status = status
    this.code = code
    this.fields = fields
    this.meta = meta
    this.params = params
    this.headers = headers
  }
}

function messageOf(messages, code, locale, params) {
  const entry = messages[code] ?? messages.validation_failed
  return interpolate(entry[locale] ?? entry.ar, params)
}

function localizeFields(fields, locale) {
  return Object.fromEntries(
    Object.entries(fields).map(([field, entries]) => [
      field,
      entries.map((entry) => interpolate(entry[locale] ?? entry.ar, entry.params)),
    ]),
  )
}

export function rejectionResponse(rejection, request, messages) {
  const locale = requestLocale(request)
  return HttpResponse.json(
    {
      data: null,
      meta: rejection.meta,
      message: messageOf(messages, rejection.code, locale, rejection.params),
      code: rejection.code,
      errors: localizeFields(rejection.fields, locale),
    },
    { status: rejection.status, headers: rejection.headers },
  )
}

export function messageEntry(messages, code, params = {}) {
  const entry = messages[code]
  return { ar: entry.ar, en: entry.en, params }
}
