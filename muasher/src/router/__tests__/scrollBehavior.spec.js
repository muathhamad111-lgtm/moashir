import { describe, expect, it } from 'vitest'
import { scrollBehavior } from '@/router/scrollBehavior'

describe('scrollBehavior', () => {
  it('leaves in-page section links to the page itself', () => {
    expect(scrollBehavior({ path: '/', hash: '#journey' }, { path: '/', hash: '' })).toBe(false)
  })

  it('starts other navigations at the top, including a hash link from another page', () => {
    expect(scrollBehavior({ path: '/kpis', hash: '' }, { path: '/', hash: '' })).toEqual({ top: 0 })
    expect(scrollBehavior({ path: '/', hash: '#tour' }, { path: '/kpis', hash: '' })).toEqual({
      top: 0,
    })
  })

  it('keeps the position when a page that opts in only changes its query', () => {
    const workspace = {
      path: '/admin/settings/criteria',
      hash: '',
      meta: { keepsScrollOnQuery: true },
    }
    expect(scrollBehavior(workspace, { path: '/admin/settings/criteria', hash: '' })).toBe(false)
    expect(scrollBehavior(workspace, { path: '/admin', hash: '' })).toEqual({ top: 0 })
    expect(
      scrollBehavior({ path: '/kpis', hash: '', meta: {} }, { path: '/kpis', hash: '' }),
    ).toEqual({ top: 0 })
  })
})
