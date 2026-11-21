import { computed, ref } from 'vue'

function sameText(left, right) {
  return (left ?? '').trim() === (right ?? '').trim()
}

export function useCommitteeNotes() {
  const saved = ref({ subs: {}, general: '' })
  const draft = ref({ subs: {}, general: '' })

  const dirty = computed(() => Boolean(changedPayload()))

  function resetFrom(notes) {
    saved.value = { subs: { ...notes.subs }, general: notes.general ?? '' }
    draft.value = { subs: { ...notes.subs }, general: notes.general ?? '' }
  }

  function noteOf(subId) {
    return draft.value.subs[String(subId)] ?? ''
  }

  function setNote(subId, text) {
    draft.value = { ...draft.value, subs: { ...draft.value.subs, [String(subId)]: text } }
  }

  function setGeneral(text) {
    draft.value = { ...draft.value, general: text }
  }

  function changedPayload() {
    const keys = new Set([...Object.keys(saved.value.subs), ...Object.keys(draft.value.subs)])
    const notes = Object.fromEntries(
      [...keys]
        .filter((key) => !sameText(saved.value.subs[key], draft.value.subs[key]))
        .map((key) => [key, (draft.value.subs[key] ?? '').trim() || null]),
    )
    const generalChanged = !sameText(saved.value.general, draft.value.general)
    if (!Object.keys(notes).length && !generalChanged) return null
    return { notes, ...(generalChanged ? { generalNote: draft.value.general.trim() || null } : {}) }
  }

  return { draft, dirty, resetFrom, noteOf, setNote, setGeneral, changedPayload }
}
