const BUILT_ASSET = '/assets/'

let bootSignature = ''

// The hashed entry script and stylesheets identify a build: a deploy that changes code or styles
// changes their names. A development server serves no hashed assets, so its signature is empty.
export function bundleSignatureOf(root) {
  const scripts = [...root.querySelectorAll('script[type="module"][src]')].map((el) =>
    el.getAttribute('src'),
  )
  const styles = [...root.querySelectorAll('link[rel="stylesheet"][href]')].map((el) =>
    el.getAttribute('href'),
  )
  return [...scripts, ...styles]
    .filter((url) => url.includes(BUILT_ASSET))
    .sort()
    .join('|')
}

export function bundleSignatureOfHtml(html) {
  return bundleSignatureOf(new DOMParser().parseFromString(html, 'text/html'))
}

// Read once at start-up, before lazily loaded routes add stylesheets of their own.
export function rememberBootBundle(root = document) {
  bootSignature = bundleSignatureOf(root)
}

export function bootBundleSignature() {
  return bootSignature
}
