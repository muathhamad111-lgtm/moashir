import { ROLES } from '@/constants/roles'
import { sessionCan } from '../sessionPermissions'
import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { isFrozen, isOpen } from '../assessments/assessmentsStore'
import { ACCREDITED_PERMISSIONS, authorizeReview } from './accreditedAccess'
import { activeAssignment } from './accreditedRecords'
import { staffNameOf } from './staffDirectory'

// Internal review notes (operator 2026-11-22), mirroring the API: staff notes per main criterion,
// sub-criterion and indicator, one current note per author and unit, never shown to the entity.
export const REVIEW_NOTE_PERMISSIONS = {
  READ: 'requests.notes.read',
  WRITE: 'requests.notes.write',
}
const NOTE_MAX = 500
const LEVELS = ['main', 'sub', 'point']
let nextNoteId = 1

function reviewable(user, record) {
  try {
    authorizeReview(user, record)
    return true
  } catch {
    return false
  }
}

const isActiveAssignee = (user, record) => activeAssignment(record)?.assessorId === user.id

export function canReadReviewNotes(user, record) {
  if (user.superAdmin) return true
  return sessionCan(REVIEW_NOTE_PERMISSIONS.READ) && reviewable(user, record)
}

export function canWriteReviewNotes(user, record) {
  if (!isOpen(record) || isFrozen(record)) return false
  if (user.superAdmin) return true
  if (!sessionCan(REVIEW_NOTE_PERMISSIONS.WRITE) || !canReadReviewNotes(user, record)) return false
  return sessionCan(ACCREDITED_PERMISSIONS.QUEUE_VIEW) || isActiveAssignee(user, record)
}

function authorRoleOf(user, record) {
  if (user.superAdmin) return 'super_admin'
  if (isActiveAssignee(user, record)) return 'assessor'
  return user.roles.includes(ROLES.REQUEST_FOLLOWER) ? 'follower' : 'admin'
}

const sameUnit = (a, b) =>
  a.level === b.level &&
  a.mainCriterionId === b.mainCriterionId &&
  (a.subCriterionId ?? null) === (b.subCriterionId ?? null) &&
  (a.guidingPointId ?? null) === (b.guidingPointId ?? null)

function noteResource(note, viewer) {
  return {
    id: note.id,
    level: note.level,
    mainCriterionId: note.mainCriterionId,
    subCriterionId: note.subCriterionId,
    guidingPointId: note.guidingPointId,
    body: note.body,
    author: { id: note.authorId, name: staffNameOf(note.authorId), role: note.authorRole },
    isMine: note.authorId === viewer.id,
    updatedAt: note.updatedAt,
  }
}

// The keys the review adds for a staff viewer; `reviewNotes` is left out for a viewer who may not read.
export function reviewNotesResource(user, record) {
  const notes = canReadReviewNotes(user, record)
    ? { reviewNotes: (record.reviewNotes ?? []).map((note) => noteResource(note, user)) }
    : {}
  return { ...notes, canWriteReviewNotes: canWriteReviewNotes(user, record) }
}

function validEntries(body) {
  const entries = Array.isArray(body?.notes) ? body.notes : []
  const fields = {}
  entries.forEach((entry, index) => {
    if (!LEVELS.includes(entry.level))
      fields[`notes.${index}.level`] = [fieldMessage('validation.invalid')]
    if ((entry.body ?? '').length > NOTE_MAX)
      fields[`notes.${index}.body`] = [fieldMessage('validation.max', { max: NOTE_MAX })]
  })
  if (!entries.length) fields.notes = [fieldMessage('validation.required')]
  if (Object.keys(fields).length) throw assessmentReject.validation(fields)
  return entries
}

function writeEntry(user, record, entry) {
  const unit = {
    level: entry.level,
    mainCriterionId: Number(entry.mainCriterionId),
    subCriterionId: entry.subCriterionId == null ? null : Number(entry.subCriterionId),
    guidingPointId: entry.guidingPointId == null ? null : Number(entry.guidingPointId),
  }
  const others = record.reviewNotes.filter(
    (note) => !(note.authorId === user.id && sameUnit(note, unit)),
  )
  const body = (entry.body ?? '').trim()
  const written = body
    ? [
        {
          ...unit,
          id: nextNoteId++,
          body,
          authorId: user.id,
          authorRole: authorRoleOf(user, record),
          updatedAt: new Date().toISOString(),
        },
      ]
    : []
  record.reviewNotes = [...others, ...written]
}

export function saveReviewNotes(user, record, body) {
  if (!canWriteReviewNotes(user, record)) throw assessmentReject.forbidden()
  if (user.superAdmin && !(body?.reason ?? '').trim()) throw assessmentReject.reasonRequired()
  record.reviewNotes ??= []
  validEntries(body).forEach((entry) => writeEntry(user, record, entry))
  return reviewNotesResource(user, record)
}
