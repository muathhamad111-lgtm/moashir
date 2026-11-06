import { http } from '../contract/http'
import { envelope, noContent } from '../contract/responses'
import {
  assessmentReject,
  assessmentRoute,
  fieldMessage,
  readJson,
} from '../stores/assessments/assessmentRejection'
import { signedInActor } from '../stores/assessments/assessmentAccess'
import { findAssessment } from '../stores/assessments/assessmentsStore'
import { assessmentResource } from '../stores/assessments/selfSerializers'
import { findEntity } from '../stores/entitiesStore'
import { financeState } from '../stores/finance/financeState'
import { expireDueOrders } from '../stores/finance/paymentLifecycle'
import {
  ACCREDITED_PERMISSIONS,
  authorizeAccreditedCurrent,
  authorizeManageOwn,
  authorizeRequestBundle,
  authorizeViewBundle,
  authorizeViewEvidence,
} from '../stores/accredited/accreditedAccess'
import { accreditedOf, boundSubsOf } from '../stores/accredited/accreditedRecords'
import { isAccreditedOpen } from '../stores/accredited/accreditedStates'
import {
  bundleResource,
  cvResource,
  evidenceItem,
  evidenceList,
  visibleEvidence,
} from '../stores/accredited/accreditedSerializers'
import { saveAccreditedCv } from '../stores/accredited/entityWorkflow'
import {
  evidenceDownload,
  removeEvidence,
  uploadEvidence,
} from '../stores/accredited/evidenceFiles'
import { bundleDownload, bundleOf, requestBundle } from '../stores/accredited/evidenceBundles'
import { isPositiveInteger } from '../stores/accredited/accreditedValidation'
import { runAccreditedSchedule } from '../stores/accredited/scheduler'
import { accreditedProgressOf } from '../stores/accredited/accreditedProgress'

const ASSESSMENT = '/api/v1/assessments/:assessment'
const NUMERIC_ID = /^\d+$/
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i
const OPEN_ORDER = ['created', 'pending', 'under-review']

function boundRecord(params) {
  runAccreditedSchedule()
  const record = findAssessment(params.assessment)
  if (!record) throw assessmentReject.notFound()
  return record
}

function numericRoute(resolve) {
  const route = assessmentRoute(resolve)
  return (info) => (NUMERIC_ID.test(info.params.assessment) ? route(info) : undefined)
}

function uuidRoute(key, resolve) {
  const route = numericRoute(resolve)
  return (info) => (UUID.test(info.params[key]) ? route(info) : undefined)
}

function awaitingPaymentOf(entityId) {
  expireDueOrders()
  const order = financeState().payments.find(
    (payment) =>
      payment.entityId === entityId &&
      payment.purpose === 'accredited' &&
      OPEN_ORDER.includes(payment.status),
  )
  return order
    ? {
        paymentId: order.id,
        reference: order.reference,
        channel: order.channel ?? 'electronic',
        status: order.status,
        expiresAt: order.expiresAt,
      }
    : null
}

function latestClosedOf(records) {
  const closed = records.filter((record) => !isAccreditedOpen(record.status))
  const stamp = (record) => record.completedAt ?? record.refundedAt ?? record.updatedAt
  return closed.sort((a, b) => (stamp(a) < stamp(b) ? 1 : -1))[0] ?? null
}

function currentResource(user) {
  if (!findEntity(user.entityId)) throw assessmentReject.entityRequired()
  runAccreditedSchedule()
  const records = accreditedOf(user.entityId)
  const active = records.find((record) => isAccreditedOpen(record.status)) ?? null
  const closed = latestClosedOf(records)
  return {
    awaitingPayment: awaitingPaymentOf(Number(user.entityId)),
    active: active
      ? { ...assessmentResource(active), progress: accreditedProgressOf(active) }
      : null,
    latestClosed: closed ? assessmentResource(closed) : null,
  }
}

function evidenceFilterOf(request) {
  const query = new URL(request.url).searchParams
  const unknown = [...query.keys()].some(
    (key) => key.startsWith('filter[') && key !== 'filter[subCriterionId]',
  )
  const subId = query.get('filter[subCriterionId]')
  if (unknown || (subId && !isPositiveInteger(subId)))
    throw assessmentReject.validation({
      filter: [fieldMessage('validation.invalid', {}, 'filter')],
    })
  return subId ? Number(subId) : null
}

function boundBundle(user, record, params) {
  const bundle = bundleOf(record, params.bundle)
  authorizeViewBundle(user, record, bundle)
  return bundle
}

export const accreditedHandlers = [
  http.get(
    '/api/v1/assessments/accredited/current',
    assessmentRoute(() => {
      const user = signedInActor()
      authorizeAccreditedCurrent(user)
      return envelope(currentResource(user))
    }),
  ),

  http.put(
    `${ASSESSMENT}/cv`,
    numericRoute(async ({ params, request }) => {
      const user = signedInActor()
      const record = boundRecord(params)
      authorizeManageOwn(user, record, ACCREDITED_PERMISSIONS.ACCREDITED_MANAGE)
      return envelope(cvResource(saveAccreditedCv(user, record, await readJson(request))))
    }),
  ),

  http.get(
    `${ASSESSMENT}/evidence`,
    numericRoute(({ params, request }) => {
      const user = signedInActor()
      const record = boundRecord(params)
      authorizeViewEvidence(user, record)
      const subId = evidenceFilterOf(request)
      const subs = boundSubsOf(record)
      const rows = visibleEvidence(record, user).filter(
        (row) => subId === null || subs.find((sub) => sub.code === row.subCode)?.id === subId,
      )
      return envelope(evidenceList(rows, record))
    }),
  ),

  http.post(
    `${ASSESSMENT}/evidence`,
    numericRoute(async ({ params, request }) => {
      const user = signedInActor()
      const record = boundRecord(params)
      authorizeManageOwn(user, record, ACCREDITED_PERMISSIONS.EVIDENCE_MANAGE)
      const row = await uploadEvidence(user, record, await request.formData())
      return envelope(evidenceItem(row, boundSubsOf(record)), {}, { status: 201 })
    }),
  ),

  http.post(
    `${ASSESSMENT}/evidence/bundles`,
    numericRoute(({ params }) => {
      const user = signedInActor()
      const record = boundRecord(params)
      authorizeRequestBundle(user, record)
      const { bundle, queued } = requestBundle(user, record)
      return envelope(bundleResource(bundle, record), {}, { status: queued ? 202 : 201 })
    }),
  ),

  http.get(
    `${ASSESSMENT}/evidence/bundles/:bundle`,
    uuidRoute('bundle', ({ params }) => {
      const user = signedInActor()
      const record = boundRecord(params)
      return envelope(bundleResource(boundBundle(user, record, params), record))
    }),
  ),

  http.post(
    `${ASSESSMENT}/evidence/bundles/:bundle/download`,
    uuidRoute('bundle', ({ params }) => {
      const user = signedInActor()
      const record = boundRecord(params)
      return envelope(bundleDownload(user, record, boundBundle(user, record, params)))
    }),
  ),

  http.delete(
    `${ASSESSMENT}/evidence/:evidence`,
    uuidRoute('evidence', async ({ params, request }) => {
      const user = signedInActor()
      const record = boundRecord(params)
      authorizeManageOwn(user, record, ACCREDITED_PERMISSIONS.EVIDENCE_MANAGE)
      removeEvidence(user, record, params.evidence, await readJson(request))
      return noContent()
    }),
  ),

  http.post(
    `${ASSESSMENT}/evidence/:evidence/download`,
    uuidRoute('evidence', ({ params }) => {
      const user = signedInActor()
      const record = boundRecord(params)
      authorizeViewEvidence(user, record)
      return envelope(evidenceDownload(user, record, params.evidence))
    }),
  ),
]
