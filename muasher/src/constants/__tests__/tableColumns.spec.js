import process from 'node:process'
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join, resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import { TABLE_COLUMNS } from '@/constants/tableColumns'

const SERVER_LIST = resolve(process.cwd(), '../backend/resources/data/table-columns.json')
const SOURCE = resolve(process.cwd(), 'src')

function sourceFiles(dir) {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)
    if (name === '__tests__' || name === 'mocks') return []
    if (statSync(path).isDirectory()) return sourceFiles(path)
    return name.endsWith('.vue') ? [path] : []
  })
}

describe('table column choices', () => {
  it('offers the same tables and columns the server accepts', () => {
    const server = JSON.parse(readFileSync(SERVER_LIST, 'utf8'))
    const spa = Object.fromEntries(
      Object.entries(TABLE_COLUMNS).map(([table, { columns }]) => [table, columns]),
    )
    expect(spa).toEqual(server)
  })

  it('starts hidden only columns the table offers', () => {
    for (const { columns, hidden = [] } of Object.values(TABLE_COLUMNS))
      expect(hidden.every((key) => columns.includes(key))).toBe(true)
  })

  it('names every listed table on a list in the app', () => {
    const used = sourceFiles(SOURCE)
      .map((path) => readFileSync(path, 'utf8'))
      .join('\n')
    const missing = Object.keys(TABLE_COLUMNS).filter(
      (table) => !used.includes(`table-key="${table}"`) && !used.includes(`entities-\${columnSet}`),
    )
    expect(missing.filter((table) => !table.startsWith('entities-'))).toEqual([])
  })
})
