import { createRequire } from 'node:module'

export const FONT_AWESOME_PACKAGE = '@fortawesome/fontawesome-pro'
export const FONT_AWESOME_MODULE_ID = 'virtual:moashir-font-awesome'

const RESOLVED_ID = `\0${FONT_AWESOME_MODULE_ID}`
const SELF_HOSTED_STYLES = '/src/styles/vendor/font-awesome.scss'

export function isFontAwesomeProInstalled(resolveFrom = import.meta.url) {
  try {
    createRequire(resolveFrom).resolve(`${FONT_AWESOME_PACKAGE}/package.json`)
    return true
  } catch {
    return false
  }
}

export function fontAwesomeModuleSource(selfHosted) {
  if (!selfHosted) return "export const fontAwesomeSource = 'kit'\n"
  return `import '${SELF_HOSTED_STYLES}'\nexport const fontAwesomeSource = 'self-hosted'\n`
}

export function fontAwesome({ selfHosted = isFontAwesomeProInstalled() } = {}) {
  return {
    name: 'moashir-font-awesome',
    resolveId(id) {
      return id === FONT_AWESOME_MODULE_ID ? RESOLVED_ID : null
    },
    load(id) {
      return id === RESOLVED_ID ? fontAwesomeModuleSource(selfHosted) : null
    },
  }
}
