const LEFT_TO_RIGHT_ISOLATE = '⁦'
const POP_DIRECTIONAL_ISOLATE = '⁩'

export function isolate(value) {
  if (value === null || value === undefined || value === '') return ''
  return `${LEFT_TO_RIGHT_ISOLATE}${value}${POP_DIRECTIONAL_ISOLATE}`
}
