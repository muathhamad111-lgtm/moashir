import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope, nextTick, ref } from 'vue'
import { useAdoptedStyleSheet } from '@/composables/useAdoptedStyleSheet'

class FakeSheet {
  replaceSync(css) {
    this.css = css
  }
}

describe('useAdoptedStyleSheet', () => {
  beforeEach(() => {
    vi.stubGlobal('CSSStyleSheet', FakeSheet)
    document.adoptedStyleSheets = []
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    delete document.adoptedStyleSheets
  })

  it('applies the CSS through a constructable sheet, follows changes and removes it on dispose', async () => {
    const css = ref('a{color:red}')
    const scope = effectScope()
    scope.run(() => useAdoptedStyleSheet(css))
    expect(document.adoptedStyleSheets).toHaveLength(1)
    expect(document.adoptedStyleSheets[0].css).toBe('a{color:red}')
    css.value = ''
    await nextTick()
    expect(document.adoptedStyleSheets[0].css).toBe('')
    scope.stop()
    expect(document.adoptedStyleSheets).toHaveLength(0)
  })

  it('does nothing where constructable sheets are not supported', () => {
    delete document.adoptedStyleSheets
    const scope = effectScope()
    expect(() => scope.run(() => useAdoptedStyleSheet(ref('a{}')))).not.toThrow()
    scope.stop()
  })
})
