import { MOYASAR_FORM_ASSETS } from '@/constants/payments'

const loads = new Map()

export class MoyasarUnavailable extends Error {
  constructor() {
    super('moyasar_unavailable')
    this.name = 'MoyasarUnavailable'
  }
}

export function moyasarAssetsOf(checkout) {
  return {
    scriptUrl: checkout?.script?.url ?? MOYASAR_FORM_ASSETS.scriptUrl,
    integrity: checkout?.script?.integrity ?? null,
    stylesheetUrl: MOYASAR_FORM_ASSETS.stylesheetUrl,
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
    script.addEventListener('load', () => (window.Moyasar ? resolve(window.Moyasar) : reject(new MoyasarUnavailable())))
    script.addEventListener('error', () => reject(new MoyasarUnavailable()))
    document.head.append(script)
  })
}

export function loadMoyasarForm(assets) {
  if (window.Moyasar) return Promise.resolve(window.Moyasar)
  if (!loads.has(assets.scriptUrl)) {
    attachStylesheet(assets.stylesheetUrl)
    const load = injectScript(assets).catch((error) => {
      loads.delete(assets.scriptUrl)
      document.head.querySelector(`script[data-moyasar-form="${assets.scriptUrl}"]`)?.remove()
      throw error
    })
    loads.set(assets.scriptUrl, load)
  }
  return loads.get(assets.scriptUrl)
}
