const loads = new Map()
const SCRIPT_EXTENSION = /\.js(?=$|[?#])/

export class MoyasarUnavailable extends Error {
  constructor() {
    super('moyasar_unavailable')
    this.name = 'MoyasarUnavailable'
  }
}

export function stylesheetUrlOf(scriptUrl) {
  return SCRIPT_EXTENSION.test(scriptUrl ?? '') ? scriptUrl.replace(SCRIPT_EXTENSION, '.css') : null
}

export function moyasarAssetsOf(checkout) {
  const scriptUrl = checkout?.script?.url || null
  return {
    scriptUrl,
    integrity: checkout?.script?.integrity || null,
    stylesheetUrl: stylesheetUrlOf(scriptUrl),
  }
}

function attachStylesheet(url) {
  if (document.head.querySelector(`link[data-moyasar-form="${url}"]`)) return
  const link = document.createElement('link')
  link.rel = 'stylesheet'
  link.href = url
  link.dataset.moyasarForm = url
  document.head.append(link)
}

function injectScript({ scriptUrl, integrity }) {
  return new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.src = scriptUrl
    script.async = true
    script.dataset.moyasarForm = scriptUrl
    if (integrity) {
      script.integrity = integrity
      script.crossOrigin = 'anonymous'
    }
    script.addEventListener('load', () =>
      window.Moyasar ? resolve(window.Moyasar) : reject(new MoyasarUnavailable()),
    )
    script.addEventListener('error', () => reject(new MoyasarUnavailable()))
    document.head.append(script)
  })
}

export function loadMoyasarForm(assets) {
  if (window.Moyasar) return Promise.resolve(window.Moyasar)
  if (!assets.scriptUrl) return Promise.reject(new MoyasarUnavailable())
  if (!loads.has(assets.scriptUrl)) {
    if (assets.stylesheetUrl) attachStylesheet(assets.stylesheetUrl)
    const load = injectScript(assets).catch((error) => {
      loads.delete(assets.scriptUrl)
      document.head.querySelector(`script[data-moyasar-form="${assets.scriptUrl}"]`)?.remove()
      throw error
    })
    loads.set(assets.scriptUrl, load)
  }
  return loads.get(assets.scriptUrl)
}
