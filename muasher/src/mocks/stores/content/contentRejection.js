import { delay } from 'msw'
import { MockRejection, rejectionResponse } from '../../contract/rejection'
import { interpolate } from '../../contract/locale'
import { contentFieldMessages, contentMessages } from '../../fixtures/contentMessages'

export const CONTENT_LATENCY_MS = 120

function bilingual(entry, params = {}) {
  return { ar: interpolate(entry.ar, params), en: interpolate(entry.en, params) }
}

export function fieldMessage(key, params = {}) {
  return bilingual(contentFieldMessages[key], params)
}

function codeMessage(code, params = {}) {
  return bilingual(contentMessages[code], params)
}

function codeOnField(status, code, field, { params = {}, meta = {} } = {}) {
  return new MockRejection(status, code, {
    fields: { [field]: [codeMessage(code, params)] },
    params,
    meta,
  })
}

export const contentReject = {
  unauthenticated: () => new MockRejection(401, 'unauthenticated'),
  forbidden: () => new MockRejection(403, 'forbidden'),
  notFound: () => new MockRejection(404, 'not_found'),
  pageNotPublished: () => new MockRejection(404, 'cms.page_not_published'),
  validation: (fields) => new MockRejection(422, 'validation_failed', { fields }),
  reasonRequired: () => codeOnField(422, 'reason_required', 'reason'),
  illegalTransition: () => new MockRejection(409, 'state.transition_illegal'),
  sectionKeyTaken: () => codeOnField(422, 'cms.section_key_taken', 'key'),
  limitReached: (limit) => new MockRejection(422, 'cms.limit_reached', { meta: { limit } }),
  pageEmpty: () => new MockRejection(422, 'cms.page_empty'),
  orderIncomplete: () => codeOnField(422, 'order.incomplete', 'orderedIds'),
  fileNotUsable: (field) => codeOnField(422, 'file.not_usable', field),
  fileContentMismatch: () => codeOnField(422, 'file.content_mismatch', 'file'),
  slugTaken: () => codeOnField(422, 'slug.taken', 'slug'),
  featuredLimitReached: (limit) =>
    codeOnField(422, 'kpi.featured_limit_reached', 'showOnHomepage', {
      params: { limit },
      meta: { limit },
    }),
  measurementExists: () => codeOnField(422, 'kpi.measurement_exists', 'measuredOn'),
  catalogInUse: (kind, kpiCount) =>
    new MockRejection(409, `${kind}.in_use`, { meta: { kpiCount } }),
  tokenUnknown: () => codeOnField(422, 'token.unknown', 'accentToken'),
  contrastFail: (pairs) => codeOnField(422, 'contrast_fail', 'accentToken', { meta: { pairs } }),
}

export function contentRoute(resolve) {
  return async (info) => {
    await delay(CONTENT_LATENCY_MS)
    try {
      return await resolve(info)
    } catch (error) {
      if (!(error instanceof MockRejection)) throw error
      return rejectionResponse(error, info.request, contentMessages)
    }
  }
}

export async function readJson(request) {
  const text = await request.text()
  return text ? JSON.parse(text) : {}
}
