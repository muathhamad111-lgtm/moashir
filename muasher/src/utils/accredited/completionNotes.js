import { ACCREDITED_LIMITS, COMPLETION_ITEM_KINDS } from '@/constants/accredited'
import { EVALUATION_TYPES } from '@/constants/criteria'
import { REVIEW_NOTE_LEVELS } from '@/utils/assessments/reviewNotes'
import { cvItemKey } from '@/utils/accredited/completionForm'

// The viewer's own internal notes by unit id, to pre-fill a completion request (operator 2026-11-22).
export function ownNotesIndex(reviewNotes) {
  const index = { main: new Map(), sub: new Map(), point: new Map() }
  const idOf = {
    [REVIEW_NOTE_LEVELS.MAIN]: (note) => note.mainCriterionId,
    [REVIEW_NOTE_LEVELS.SUB]: (note) => note.subCriterionId,
    [REVIEW_NOTE_LEVELS.POINT]: (note) => note.guidingPointId,
  }
  for (const note of reviewNotes ?? []) {
    if (note.isMine && idOf[note.level]) index[note.level].set(idOf[note.level](note), note.body)
  }
  return index
}

const NOTE_SEPARATOR = ' · '

function pointLines(sub, index) {
  return (sub.points ?? [])
    .filter((point) => index.point.has(point.id))
    .map((point) => `${point.code} — ${index.point.get(point.id)}`)
}

function evidenceNote(option, index, cvSelected) {
  const subNote = cvSelected ? '' : (index.sub.get(option.sub.id) ?? '')
  if (option.sub.evaluationType !== EVALUATION_TYPES.GUIDING_POINTS) return subNote
  // The item note is one line, so the notes follow each other with a separator.
  return [subNote, ...pointLines(option.sub, index)].filter(Boolean).join(NOTE_SEPARATOR)
}

// Which note an item of a completion request starts with: the CV item takes the sub-criterion's
// note, the evidence item its indicators' notes (and the sub's own note when its CV item is not
// asked for), the main criterion's attachments the main note. The text stays editable.
function noteOf(option, index, selectedKeys) {
  if (!option.sub) return index.main.get(option.main.id) ?? ''
  if (option.kind === COMPLETION_ITEM_KINDS.CV) return index.sub.get(option.sub.id) ?? ''
  return evidenceNote(option, index, selectedKeys.includes(cvItemKey(option.sub.id)))
}

export function prefilledNote(option, { index, selectedKeys }) {
  return noteOf(option, index, selectedKeys).slice(0, ACCREDITED_LIMITS.COMPLETION_ITEM_NOTE_MAX)
}
