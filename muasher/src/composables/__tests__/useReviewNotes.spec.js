import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { useReviewNotes } from '@/composables/useReviewNotes'
import { assessorReviewService } from '@/services/assessorReviewService'
import { noteUnit } from '@/utils/assessments/reviewNotes'

vi.mock('@/services/assessorReviewService', () => ({
  assessorReviewService: { saveReviewNotes: vi.fn() },
}))

const UNIT = noteUnit({ main: { id: 11 }, sub: { id: 21 } })
const note = (overrides) => ({
  id: 1,
  ...UNIT,
  body: 'Saved',
  author: { id: 10, name: 'Sultan', role: 'assessor' },
  isMine: true,
  updatedAt: '2026-11-22T10:00:00Z',
  ...overrides,
})

function stateWith(reviewNotes, canWriteReviewNotes = true) {
  const review = ref({ reviewNotes, canWriteReviewNotes })
  return {
    review,
    assessment: ref({ id: 9108 }),
    patch: (changes) => (review.value = { ...review.value, ...changes }),
  }
}

describe('useReviewNotes', () => {
  it('starts from the saved note and sends only the changed units, a cleared one as null', () => {
    const notes = useReviewNotes({ reviewState: stateWith([note()]) })
    expect(notes.canRead.value).toBe(true)
    expect(notes.bodyOf(UNIT)).toBe('Saved')
    expect(notes.dirty.value).toBe(false)
    notes.setBody(UNIT, '  ')
    expect(notes.changes.value).toEqual([{ ...UNIT, body: null }])
    notes.setBody(UNIT, 'Saved')
    expect(notes.dirty.value).toBe(false)
  })

  it('saves the changes and takes the notes the API returns', async () => {
    const reviewState = stateWith([])
    const notes = useReviewNotes({ reviewState })
    const returned = [note({ body: 'New' })]
    assessorReviewService.saveReviewNotes.mockResolvedValue({
      data: { reviewNotes: returned, canWriteReviewNotes: true },
    })
    notes.setBody(UNIT, 'New')
    await notes.save({ reason: 'x' })
    expect(assessorReviewService.saveReviewNotes).toHaveBeenCalledWith(9108, {
      notes: [{ ...UNIT, body: 'New' }],
      reason: 'x',
    })
    expect(notes.bodyOf(UNIT)).toBe('New')
    expect(notes.dirty.value).toBe(false)
  })

  it('reads nothing when the API leaves the notes out, and shows a read-only viewer their own note', () => {
    expect(useReviewNotes({ reviewState: stateWith(null) }).canRead.value).toBe(false)
    const readOnly = useReviewNotes({
      reviewState: stateWith([note(), note({ id: 2, isMine: false, body: 'Follower' })], false),
    })
    expect(readOnly.threadOf(UNIT).map((entry) => entry.body)).toEqual(['Saved', 'Follower'])
  })
})
