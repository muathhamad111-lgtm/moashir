import { onScopeDispose, watch } from 'vue'

function supportsAdoptedSheets() {
  return (
    typeof document !== 'undefined' &&
    typeof CSSStyleSheet === 'function' &&
    'adoptedStyleSheets' in document &&
    'replaceSync' in CSSStyleSheet.prototype
  )
}

// Applies generated CSS through a constructable stylesheet. The production CSP
// (`style-src 'self'`) refuses an injected <style> element, but not CSSOM.
export function useAdoptedStyleSheet(cssText) {
  if (!supportsAdoptedSheets()) return
  const sheet = new CSSStyleSheet()
  document.adoptedStyleSheets = [...document.adoptedStyleSheets, sheet]

  watch(cssText, (css) => sheet.replaceSync(css ?? ''), { immediate: true })

  onScopeDispose(() => {
    document.adoptedStyleSheets = document.adoptedStyleSheets.filter((item) => item !== sheet)
  })
}
