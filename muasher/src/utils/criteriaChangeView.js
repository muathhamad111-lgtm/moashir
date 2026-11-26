// How a list of criteria changes (publish preview or edit event) is shown: grouped by main criterion,
// each change tagged by what it does to the scores of open requests.

const VERSION_NODE = 'version'
const TEXT_FIELDS = Object.freeze(['name', 'description', 'assessor_note_label', 'label'])

export const CHANGE_TAGS = Object.freeze({
  ADDED: 'added',
  REMOVED: 'removed',
  NEEDS_NEW_SCORE: 'needsNewScore',
  KEEPS_SCORE: 'keepsScore',
  TEXT_ONLY: 'textOnly',
})

const byCode = (first, second) => first.localeCompare(second, 'en', { numeric: true })

// «M1.2.3» belongs to «M1»; a change of the version itself has no main criterion.
export function mainCodeOf(change) {
  return change.nodeType === VERSION_NODE ? '' : String(change.code).split('.')[0]
}

export function isTextOnly(change) {
  if (change.change === 'text_corrected') return true
  return (
    change.change === 'modified' &&
    change.fields.length > 0 &&
    change.fields.every((field) => TEXT_FIELDS.includes(field))
  )
}

export function tagsOf(change) {
  if (change.change === 'added') return [CHANGE_TAGS.ADDED]
  if (change.change === 'removed') return [CHANGE_TAGS.REMOVED]
  if (change.scoreAffecting) return [CHANGE_TAGS.NEEDS_NEW_SCORE]
  return isTextOnly(change)
    ? [CHANGE_TAGS.KEEPS_SCORE, CHANGE_TAGS.TEXT_ONLY]
    : [CHANGE_TAGS.KEEPS_SCORE]
}

// `mainNames`: main code → name, for groups whose main criterion did not change itself.
export function groupByMain(changes, mainNames = new Map()) {
  const groups = new Map()
  const sorted = [...changes].sort((first, second) => byCode(first.code, second.code))
  sorted.forEach((change) => {
    const code = mainCodeOf(change)
    if (!groups.has(code))
      groups.set(code, { code, label: mainNames.get(code) ?? null, changes: [] })
    const group = groups.get(code)
    if (change.nodeType === 'main' && change.label) group.label = change.label
    group.changes.push(change)
  })
  return [...groups.values()].sort((first, second) => byCode(first.code, second.code))
}

// The three tiles of the "What changed" page (design §B.3.6); a removed item needs new scores too.
export const CHANGE_TILES = Object.freeze(['keepsScore', 'needsNewScore', 'added'])

export function tileOf(change) {
  if (change.change === 'added') return 'added'
  if (change.change === 'removed' || change.scoreAffecting) return 'needsNewScore'
  return 'keepsScore'
}

export function countByTile(changes) {
  const counts = Object.fromEntries(CHANGE_TILES.map((tile) => [tile, 0]))
  changes.forEach((change) => (counts[tileOf(change)] += 1))
  return counts
}

// `textOf(change)`: what a search looks in (its code and name in the current language).
export function filterChanges(changes, { tile = null, query = '', textOf }) {
  const needle = query.trim().toLocaleLowerCase()
  return changes.filter(
    (change) =>
      (!tile || tileOf(change) === tile) &&
      (!needle || textOf(change).toLocaleLowerCase().includes(needle)),
  )
}
