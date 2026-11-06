import { delay } from 'msw'
import { MockRejection, rejectionResponse } from '../../contract/rejection'
import { interpolate } from '../../contract/locale'
import { assessmentAttributes, assessmentMessages } from '../../fixtures/assessmentMessages'

export const ASSESSMENT_LATENCY_MS = 120

export function fieldMessage(code, params = {}, attribute = null) {
  const entry = assessmentMessages[code]
  const label = attribute ? assessmentAttributes[attribute] : null
  return {
    ar: interpolate(entry.ar, { ...params, attribute: label?.ar ?? '' }),
    en: interpolate(entry.en, { ...params, attribute: label?.en ?? '' }),
  }
}

function single(code, params, attribute) {
  return [fieldMessage(code, params, attribute)]
}

export const assessmentReject = {
  unauthenticated: () => new MockRejection(401, 'unauthenticated'),
  forbidden: () => new MockRejection(403, 'forbidden'),
  notFound: () => new MockRejection(404, 'not_found'),
  validation: (fields) => new MockRejection(422, 'validation_failed', { fields }),
  entityRequired: () => new MockRejection(403, 'assessment.entity_required'),
  entityNotActive: () => new MockRejection(403, 'assessment.entity_not_active'),
  entityDeleted: () => new MockRejection(409, 'entity.deleted'),
  noPublishedVersion: () => new MockRejection(404, 'criteria.no_published_version'),
  draftExists: (assessmentId) =>
    new MockRejection(409, 'self.draft_exists', { meta: { assessmentId } }),
  illegalTransition: (state, attempted) =>
    new MockRejection(409, 'state.transition_illegal', {
      fields: { state: [state], attempted: [attempted] },
    }),
  criteriaUpdateInProgress: () => new MockRejection(409, 'assessment.criteria_update_in_progress'),
  reasonRequired: () =>
    new MockRejection(422, 'reason_required', { fields: { reason: single('reason_required') } }),
  scoresIncomplete: (missing) =>
    new MockRejection(422, 'scores.incomplete', {
      fields: Object.fromEntries(
        missing.map((unit) => [
          `scores.${unit.level}s.${unit.id}`,
          single('scores.item_missing', { code: unit.code }),
        ]),
      ),
      meta: { missing },
    }),
  requestOpen: (assessmentId) =>
    new MockRejection(409, 'accredited.request_open', { meta: { assessmentId } }),
  paymentBeingProcessed: () => new MockRejection(409, 'accredited.payment_being_processed'),
  coded: (status, code, field = null, params = {}) =>
    new MockRejection(status, code, {
      fields: field ? { [field]: single(code, params) } : {},
      params,
    }),
  submissionIncomplete: (gaps, fields) =>
    new MockRejection(422, 'submission.incomplete', {
      fields,
      meta: { missingCv: gaps.missingCv, missingAttachments: gaps.missingAttachments },
    }),
  subFull: (max) =>
    new MockRejection(422, 'attachment.sub_full', {
      fields: { file: single('attachment.sub_full', { max }) },
      meta: { maxFilesPerSub: max },
      params: { max },
    }),
  notAssignee: () => new MockRejection(403, 'assessment.not_assignee'),
  notQualified: (qualification, path) =>
    new MockRejection(422, 'assessor.not_qualified', {
      fields: { assessorId: single('assessor.not_qualified') },
      meta: { requiredQualification: qualification, path },
    }),
  invalidScoreItems: (reasonsByField) => {
    const reasons = [...new Set(Object.values(reasonsByField))]
    const fields = Object.fromEntries(
      Object.entries(reasonsByField).map(([field, reason]) => [field, single(reason)]),
    )
    return new MockRejection(422, reasons.length === 1 ? reasons[0] : 'validation_failed', {
      fields,
    })
  },
}

function localizedFields(fields) {
  return Object.fromEntries(
    Object.entries(fields).map(([field, entries]) => [
      field,
      entries.map((entry) => (typeof entry === 'string' ? { ar: entry, en: entry } : entry)),
    ]),
  )
}

export function assessmentRoute(resolve) {
  return async (info) => {
    await delay(ASSESSMENT_LATENCY_MS)
    try {
      return await resolve(info)
    } catch (error) {
      if (!(error instanceof MockRejection)) throw error
      error.fields = localizedFields(error.fields)
      return rejectionResponse(error, info.request, assessmentMessages)
    }
  }
}

export async function readJson(request) {
  const text = await request.text()
  return text ? JSON.parse(text) : {}
}
