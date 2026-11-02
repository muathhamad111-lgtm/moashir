import { HttpResponse } from 'msw'
import { criteriaErrorMessages } from '../fixtures/criteriaMessages'
import { interpolate, requestLocale } from '../contract/locale'

export { interpolate, requestLocale }

export class CriteriaRejection extends Error {
  constructor(status, code, { fields = {}, meta = {}, params = {} } = {}) {
    super(code)
    this.status = status
    this.code = code
    this.fields = fields
    this.meta = meta
    this.params = params
  }
}

export function localizedMessage(code, locale, params) {
  const entry = criteriaErrorMessages[code] ?? criteriaErrorMessages.validation_failed
  return interpolate(entry[locale] ?? entry.ar, params)
}

function localizeFields(fields, locale) {
  return Object.fromEntries(
    Object.entries(fields).map(([field, messages]) => [
      field,
      messages.map((message) =>
        typeof message === 'string' ? message : interpolate(message[locale], message.params),
      ),
    ]),
  )
}

export function rejectionResponse(rejection, request) {
  const locale = requestLocale(request)
  return HttpResponse.json(
    {
      data: null,
      meta: rejection.meta,
      message: localizedMessage(rejection.code, locale, rejection.params),
      code: rejection.code,
      errors: localizeFields(rejection.fields, locale),
    },
    { status: rejection.status },
  )
}

export function fieldMessage(code, params = {}) {
  const entry = criteriaErrorMessages[code]
  return { ar: entry.ar, en: entry.en, params }
}

export const reject = {
  unauthenticated: () => new CriteriaRejection(401, 'unauthenticated'),
  forbidden: () => new CriteriaRejection(403, 'forbidden'),
  notFound: () => new CriteriaRejection(404, 'not_found'),
  validation: (fields) => new CriteriaRejection(422, 'validation_failed', { fields }),
  noDraft: () => new CriteriaRejection(409, 'criteria.no_draft'),
  draftExists: () => new CriteriaRejection(409, 'criteria.draft_exists'),
  notDraft: (status) =>
    new CriteriaRejection(409, 'criteria.version_not_draft', {
      fields: { versionStatus: [status] },
    }),
  isDraft: () =>
    new CriteriaRejection(409, 'criteria.version_is_draft', {
      fields: { versionStatus: ['draft'] },
    }),
  noPublished: () => new CriteriaRejection(404, 'criteria.no_published_version'),
  cloneSourceInvalid: () =>
    new CriteriaRejection(422, 'criteria.clone_source_invalid', {
      fields: { cloneFromVersionId: [fieldMessage('criteria.clone_source_invalid')] },
    }),
  scopeRequired: () =>
    new CriteriaRejection(422, 'criteria.scope_required', {
      fields: { scope: [fieldMessage('criteria.scope_required')] },
    }),
  exceedsRemaining: (remaining) =>
    new CriteriaRejection(422, 'criteria.weight_exceeds_remaining', {
      fields: { weight: [fieldMessage('criteria.weight_exceeds_remaining', { remaining })] },
      meta: { remainingWeight: remaining },
      params: { remaining },
    }),
  belowAllocated: (allocated) =>
    new CriteriaRejection(422, 'criteria.weight_below_allocated', {
      fields: { weight: [fieldMessage('criteria.weight_below_allocated', { allocated })] },
      meta: { allocatedWeight: allocated },
      params: { allocated },
    }),
  weightRequired: () =>
    new CriteriaRejection(422, 'criteria.weight_required', {
      fields: { weight: [fieldMessage('criteria.weight_required')] },
    }),
  tooSmallToDistribute: (weight, shares) =>
    new CriteriaRejection(422, 'criteria.weight_too_small_to_distribute', {
      fields: {
        weight: [fieldMessage('criteria.weight_too_small_to_distribute', { weight, shares })],
      },
      params: { weight, shares },
    }),
  subEvaluatedDirectly: () =>
    new CriteriaRejection(422, 'criteria.sub_evaluated_directly', {
      fields: { evaluationType: [fieldMessage('criteria.sub_evaluated_directly')] },
    }),
  pointsConfirmRequired: (count) =>
    new CriteriaRejection(422, 'criteria.points_confirm_required', {
      fields: {
        confirmDeletePoints: [fieldMessage('criteria.points_confirm_required', { count })],
      },
      meta: { impact: { pointCount: count } },
      params: { count },
    }),
  orderIncomplete: () =>
    new CriteriaRejection(422, 'order.incomplete', {
      fields: { orderedIds: [fieldMessage('order.incomplete')] },
    }),
  nodeNotInVersion: (index) =>
    new CriteriaRejection(422, 'criteria.node_not_in_version', {
      fields: { [`nodes.${index}.id`]: [fieldMessage('criteria.node_not_in_version')] },
    }),
}
