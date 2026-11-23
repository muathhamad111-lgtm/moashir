import { PROFILE_CHANGE_FILES as F } from '@/constants/entityEnums'
import { findCity, findRegion } from './regionsStore'
import { referenceOf } from './entitiesStore'
import { findFile } from './finance/financeState'
import { signedDownloadOf } from './finance/signedFiles'
import { PROFILE_CHANGE_FIELDS } from './profileChangeFields'
import { itemsOf, labelOf, staleFieldsOf } from './profileChangeRequests'

const PLACES = { region_id: findRegion, city_id: findCity }

function presented(field, value) {
  const find = PLACES[field]
  if (!find || value === null || value === undefined) return value ?? null
  const place = find(value)
  return place ? { id: place.id, code: place.code, name: { ...place.name } } : { id: value }
}

function fileValue(descriptor, viewer) {
  const file = descriptor ? findFile(descriptor.id) : null
  if (!file) return null
  return {
    id: file.id,
    name: descriptor.name ?? file.name,
    url: viewer ? signedDownloadOf(viewer, file).url : null,
    uploadedAt: descriptor.uploadedAt ?? null,
  }
}

export function profileChangeSummary(request, entity) {
  return {
    id: request.id,
    status: request.status,
    revision: request.revision,
    entity: {
      id: entity.id,
      reference: referenceOf(entity),
      name: { ar: entity.name.ar, en: entity.name.en ?? null },
    },
    items: itemsOf(request),
    changedFields: Object.keys(request.proposed),
    files: Object.keys(request.files),
    decisions: request.decisions ? { ...request.decisions } : null,
    reviewNote: request.reviewNote,
    submittedBy: request.submittedBy,
    reviewedBy: request.reviewedBy,
    submittedAt: request.submittedAt,
    updatedAt: request.updatedAt,
    decidedAt: request.decidedAt,
  }
}

function fieldRow(request, field, stale) {
  return {
    item: field,
    kind: 'field',
    section: PROFILE_CHANGE_FIELDS[field].section,
    label: labelOf(field),
    oldValue: presented(field, request.base[field]),
    newValue: presented(field, request.proposed[field]),
    isStale: stale.includes(field),
    decision: request.decisions?.[field] ?? null,
  }
}

function fileRow(request, entity, kind, viewer) {
  const change = request.files[kind]
  const isPending = request.decidedAt === null
  const approved = kind === F.LOGO ? entity.logo : entity.vatCertificate
  return {
    item: kind,
    kind: 'file',
    section: kind === F.VAT_CERTIFICATE ? 'billing' : null,
    label: labelOf(kind),
    oldValue: isPending ? fileValue(approved, viewer) : null,
    newValue: isPending ? fileValue(change.upload, viewer) : null,
    removes: change.removes,
    isStale: false,
    decision: request.decisions?.[kind] ?? null,
  }
}

export function profileChangeDetail(request, entity, viewer) {
  const stale = staleFieldsOf(request, entity)
  return {
    ...profileChangeSummary(request, entity),
    staleFields: stale,
    changes: [
      ...Object.keys(request.proposed).map((field) => fieldRow(request, field, stale)),
      ...Object.keys(request.files).map((kind) => fileRow(request, entity, kind, viewer)),
    ],
  }
}

export function ownPendingSummary(request) {
  if (!request) return null
  return {
    id: request.id,
    revision: request.revision,
    items: itemsOf(request),
    submittedAt: request.submittedAt,
    updatedAt: request.updatedAt,
  }
}
