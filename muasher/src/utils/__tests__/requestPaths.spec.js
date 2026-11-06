import { describe, expect, it } from 'vitest'
import {
  assignedPathsOf,
  followedPathsOf,
  overdueFromLink,
  qualifiedPathsOf,
  selectedPathOf,
  statusForPath,
} from '@/utils/requestPaths'

describe('request paths', () => {
  it('follows every reviewed path unless the session scope limits it', () => {
    expect(followedPathsOf(null)).toEqual(['accredited', 'advanced'])
    expect(followedPathsOf({ requestScope: { allPaths: true, paths: [] } })).toEqual([
      'accredited',
      'advanced',
    ])
    expect(followedPathsOf({ requestScope: { allPaths: false, paths: ['advanced'] } })).toEqual([
      'advanced',
    ])
  })

  it('maps the assessor qualifications to paths in the reviewed order', () => {
    expect(
      qualifiedPathsOf({ assessorQualifications: ['advanced_initial', 'accredited'] }),
    ).toEqual(['accredited', 'advanced'])
    expect(qualifiedPathsOf({ assessorQualifications: [] })).toEqual([])
    expect(
      assignedPathsOf({ assessorQualifications: ['accredited'] }, [{ path: 'advanced' }]),
    ).toEqual(['accredited', 'advanced'])
  })

  it('serves the requested path only when it is followed, else the first one', () => {
    expect(selectedPathOf('advanced', ['accredited', 'advanced'])).toBe('advanced')
    expect(selectedPathOf('advanced', ['accredited'])).toBe('accredited')
    expect(selectedPathOf(undefined, [])).toBe('accredited')
  })

  it('maps a dashboard status list to the one status of the selected path', () => {
    const link = 'submitted,awaiting_review_assignment'
    expect(statusForPath(link, 'accredited')).toBe('submitted')
    expect(statusForPath(link, 'advanced')).toBe('awaiting-review-assignment')
    expect(statusForPath('declined_incomplete', 'accredited')).toBe('declined')
    expect(statusForPath('assigned', 'advanced')).toBe('assigned')
    expect(statusForPath('completed', 'advanced')).toBe('')
    expect(statusForPath(undefined, 'accredited')).toBe('')
  })

  it('keeps only true or false from the overdue link', () => {
    expect(overdueFromLink('true')).toBe('true')
    expect(overdueFromLink('false')).toBe('false')
    expect(overdueFromLink('yes')).toBe('')
  })
})
