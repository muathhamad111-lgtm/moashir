import { readFileSync, readdirSync } from 'node:fs'
import { join, relative, resolve } from 'node:path'
import process from 'node:process'
import { describe, expect, it } from 'vitest'

const SRC = resolve(process.cwd(), 'src')
const ROLE_WEIGHTS = ['body', 'emphasis', 'heading', 'figure', 'display'].map(
  (role) => `var(--fw-${role})`,
)
const ALLOWED = new Set([...ROLE_WEIGHTS, 'inherit'])
// @font-face ranges and the text() mixin's parameter are the only places a weight is not a role token.
const EXEMPT_FILES = new Set(['styles/base/_fonts.scss', 'styles/mixins/_typography.scss'])

function styleFiles() {
  return readdirSync(SRC, { recursive: true })
    .filter((file) => /\.(scss|vue)$/.test(file))
    .map((file) => relative(SRC, join(SRC, file)))
}

function weightsIn(file) {
  const source = readFileSync(join(SRC, file), 'utf8')
  return [...source.matchAll(/font-weight:\s*([^;]+);/g)].map(([, value]) => value.trim())
}

function hacenFaces() {
  const fonts = readFileSync(join(SRC, 'styles/base/hacen-maghreb.css'), 'utf8')
  const faces = fonts.split('@font-face').filter((face) => face.includes("'Hacen Maghreb'"))
  return Object.fromEntries(
    faces.map((face) => [
      /hacen-maghreb-(\w+)\.woff/.exec(face)[1],
      /font-weight: ([^;]+);/.exec(face)[1],
    ]),
  )
}

describe('font weights', () => {
  it('uses only the role weights in components', () => {
    const offenders = styleFiles()
      .filter((file) => !EXEMPT_FILES.has(file))
      .flatMap((file) =>
        weightsIn(file)
          .filter((value) => !ALLOWED.has(value))
          .map((value) => `${file}: ${value}`),
      )

    expect(offenders).toEqual([])
  })

  it('maps each Hacen Maghreb cut to the weights it draws like', () => {
    expect(hacenFaces()).toEqual({ light: '100 400', bold: '500 700', regular: '800 900' })
  })
})
