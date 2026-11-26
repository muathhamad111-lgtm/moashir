import { describe, expect, it } from 'vitest'
import {
  bootBundleSignature,
  bundleSignatureOfHtml,
  rememberBootBundle,
} from '@/utils/bundleSignature'

const BUILD_A = `<!doctype html><html><head>
  <link rel="icon" href="/favicon.ico">
  <script type="module" crossorigin src="/assets/index-AAA.js"></script>
  <link rel="modulepreload" href="/assets/vue-111.js">
  <link rel="stylesheet" crossorigin href="/assets/index-CSSA.css">
</head><body><div id="app"></div></body></html>`

describe('bundle signature', () => {
  it('names a build by its hashed entry script and stylesheets only', () => {
    expect(bundleSignatureOfHtml(BUILD_A)).toBe('/assets/index-AAA.js|/assets/index-CSSA.css')
  })

  it('changes when only the styles change', () => {
    const stylesOnly = BUILD_A.replace('index-CSSA.css', 'index-CSSB.css')
    expect(bundleSignatureOfHtml(stylesOnly)).not.toBe(bundleSignatureOfHtml(BUILD_A))
  })

  it('is empty for a development page, which serves no hashed bundle', () => {
    expect(
      bundleSignatureOfHtml(
        '<html><head><script type="module" src="/src/main.js"></script></head></html>',
      ),
    ).toBe('')
  })

  it('remembers the signature of the page the tab started with', () => {
    rememberBootBundle(new DOMParser().parseFromString(BUILD_A, 'text/html'))
    expect(bootBundleSignature()).toBe('/assets/index-AAA.js|/assets/index-CSSA.css')
  })
})
