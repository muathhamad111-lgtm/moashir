import { readdirSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import process from 'node:process'
import { describe, expect, it } from 'vitest'

// Signed-in pages take their width from the shell: `.app-layout__content` and
// `.portal-layout__main` fill the column beside the sidebar, so collapsing the sidebar widens
// every page the same way. A page or band that caps or centres itself at a container width
// breaks that, so only the public layout may use the container widths.
const PUBLIC_LAYOUT_FILES = [
  '_footer.scss',
  '_kpi-detail.scss',
  '_kpis-page.scss',
  '_lenses.scss',
  '_public-navbar.scss',
]
const PAGE_WIDTH = /--size-(content-max|container)\b|--size-container-lg/
const STYLE_DIRS = ['src/styles/components', 'src/styles/tts', 'src/styles/mixins']

function styleFiles() {
  return STYLE_DIRS.flatMap((dir) =>
    readdirSync(resolve(process.cwd(), dir))
      .filter((name) => name.endsWith('.scss') && !PUBLIC_LAYOUT_FILES.includes(name))
      .map((name) => `${dir}/${name}`),
  )
}

function widthLinesOf(file) {
  return readFileSync(resolve(process.cwd(), file), 'utf8')
    .split('\n')
    .map((line, index) => ({ line: line.trim(), at: `${file}:${index + 1}` }))
    .filter(({ line }) => PAGE_WIDTH.test(line))
}

describe('signed-in page width', () => {
  it('leaves the page width to the shell: no signed-in style uses a container width', () => {
    const offenders = styleFiles().flatMap(widthLinesOf)
    expect(offenders).toEqual([])
  })

  it('still lets the public layout centre its content in the container', () => {
    const publicFiles = PUBLIC_LAYOUT_FILES.map((name) => `src/styles/components/${name}`)
    expect(publicFiles.flatMap(widthLinesOf).length).toBeGreaterThan(0)
  })
})
