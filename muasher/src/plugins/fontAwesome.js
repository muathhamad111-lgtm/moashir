import { fontAwesomeSource } from 'virtual:moashir-font-awesome'

export const FONT_AWESOME_KIT_ORIGIN = 'https://kit.fontawesome.com'

export function fontAwesomeKitScripts(
  source = fontAwesomeSource,
  kitId = import.meta.env.VITE_FONTAWESOME_KIT_ID,
) {
  if (source === 'self-hosted' || !kitId) return []
  return [{ src: `${FONT_AWESOME_KIT_ORIGIN}/${kitId}.js`, crossorigin: 'anonymous', defer: true }]
}

export { fontAwesomeSource }
