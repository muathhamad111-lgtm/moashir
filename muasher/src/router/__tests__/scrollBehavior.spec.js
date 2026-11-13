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
})
