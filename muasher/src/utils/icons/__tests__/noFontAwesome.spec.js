import { readdirSync, readFileSync } from 'node:fs'
import { join, relative, resolve } from 'node:path'
import process from 'node:process'
import { describe, expect, it } from 'vitest'

const ROOT = process.cwd()
const SOURCE_DIR = resolve(ROOT, 'src')
const STORED_FORMAT_FILES = [
  'src/mocks/fixtures/cmsPages.js',
  'src/mocks/fixtures/content.js',
  'src/mocks/fixtures/contentMessages.js',
  'src/mocks/fixtures/kpis.js',
]
const FONT_AWESOME_CLASS = /\bfa-(solid|regular|brands|light|thin|duotone|spin|fw)\b/
const ICON_ELEMENT = /<i\s[^>]*class=|<i\s*:class=/

function sourceFiles(dir) {
  return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const path = join(dir, entry.name)
    if (entry.isDirectory()) return entry.name === '__tests__' ? [] : sourceFiles(path)
    return /\.(vue|js|scss)$/.test(entry.name) ? [relative(ROOT, path)] : []
  })
}

describe('icon sources', () => {
  const files = sourceFiles(SOURCE_DIR).filter((file) => !STORED_FORMAT_FILES.includes(file))

  it('uses no Font Awesome class outside the stored-value adapter', () => {
    const offenders = files.filter(
      (file) =>
        !file.startsWith('src/utils/icons/') &&
        file !== 'src/constants/icons.js' &&
        FONT_AWESOME_CLASS.test(readFileSync(file, 'utf8')),
    )
    expect(offenders).toEqual([])
  })

  it('draws icons with AppIcon, never with an <i> element', () => {
    const offenders = files.filter(
      (file) => file.endsWith('.vue') && ICON_ELEMENT.test(readFileSync(file, 'utf8')),
    )
    expect(offenders).toEqual([])
  })
})
