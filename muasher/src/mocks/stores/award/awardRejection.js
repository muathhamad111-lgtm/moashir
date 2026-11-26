import { MockRejection } from '../../contract/rejection'
import { fieldMessage } from '../assessments/assessmentRejection'

function conditionMessage(check) {
  return fieldMessage(`advanced.conditions.${check.rule}`, {
    required: check.required ?? '',
    actual: check.actual ?? '',
  })
}

export function conditionsRejection(failures) {
  return new MockRejection(422, 'cycle.conditions_failed', {
    fields: { conditions: failures.map(conditionMessage) },
    meta: { conditions: failures.map((check) => ({ ...check })) },
  })
}

export const awardReject = {
  startBlocked: (block) =>
    new MockRejection(409, block.code, {
      meta: { awardCycleId: block.cycle.id, concurrency: block.concurrency },
    }),
  fieldsLocked: (fields) =>
    new MockRejection(409, 'cycle.field_locked', {
      fields: Object.fromEntries(
        fields.map((field) => [field, [fieldMessage('cycle.field_locked')]]),
      ),
      meta: { lockedFields: fields },
    }),
  requestsPending: (requests) =>
    new MockRejection(409, 'cycle.requests_pending', {
      meta: { openRequestsCount: requests.length, openRequests: requests },
    }),
  requestExists: (assessmentId) =>
    new MockRejection(409, 'advanced.request_exists', { meta: { assessmentId } }),
  overrideReasonRequired: (score, threshold) =>
    new MockRejection(422, 'filtering.override_reason_required', {
      fields: { overrideReason: [fieldMessage('filtering.override_reason_required')] },
      meta: { score, threshold },
    }),
  deadlineAfterCommittee: (date) =>
    new MockRejection(422, 'completion.deadline_after_committee_start', {
      fields: { deadlineAt: [fieldMessage('completion.deadline_after_committee_start', { date })] },
      meta: { committeeStart: date },
      params: { date },
    }),
}
