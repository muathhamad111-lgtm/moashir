import { readdirSync, readFileSync } from 'node:fs'
import { join, relative, resolve } from 'node:path'
import process from 'node:process'
import { describe, expect, it } from 'vitest'

// DEPLOY #52 regression: AuthLayout kept `ref="viewport"` in its template and a setup constant named `viewport` that
// was not a ref. The production compiler binds a static template ref to the setup binding of the same name, so the
// sign-in page crashed in the built app while the dev server (and every test on it) worked.
const ROOT = process.cwd()
const SOURCE_DIR = resolve(ROOT, 'src')
const SCRIPT_SETUP = /<script setup[^>]*>([\s\S]*?)<\/script>/
const TEMPLATE_REF = /\sref="([A-Za-z_$][\w$]*)"/g
const REF_FACTORY = /^(ref|shallowRef|useTemplateRef)\(/

function vueFiles(dir) {
  return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const path = join(dir, entry.name)
    if (entry.isDirectory()) return entry.name === '__tests__' ? [] : vueFiles(path)
    return entry.name.endsWith('.vue') ? [path] : []
  })
}

function plainConstantNamed(script, name) {
  const declaration = script.match(new RegExp(`\\bconst\\s+${name}\\s*=\\s*([^\\n]+)`))
  return Boolean(declaration) && !REF_FACTORY.test(declaration[1].trim())
}

function clashesIn(path) {
  const source = readFileSync(path, 'utf8')
  const setup = source.match(SCRIPT_SETUP)
  if (!setup) return []
  const template = source.slice(setup.index + setup[0].length)
  const names = new Set([...template.matchAll(TEMPLATE_REF)].map((match) => match[1]))
  return [...names]
    .filter((name) => plainConstantNamed(setup[1], name))
    .map((name) => `${relative(ROOT, path)}: ref="${name}"`)
}

describe('template ref names', () => {
  it('never reuse the name of a setup constant that is not a ref', () => {
    expect(vueFiles(SOURCE_DIR).flatMap(clashesIn)).toEqual([])
  })
})
