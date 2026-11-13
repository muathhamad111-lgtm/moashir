import { RICH_TEXT_BLOCKS } from '@/constants/legal'

const INVISIBLE_RANGES = Object.freeze([
  [0x00, 0x08],
  [0x0b, 0x0c],
  [0x0e, 0x1f],
  [0x7f, 0x9f],
  [0x202a, 0x202e],
  [0x2066, 0x2069],
  [0xfeff, 0xfeff],
])
const HEADING = /^(#{1,3}) +(.+?)(?: +#+)?$/u
const BULLET_ITEM = /^[-*+] +(.+)$/u
const NUMBERED_ITEM = /^(\d{1,6})[.)] +(.+)$/u
const SEPARATOR_ROW = /^\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?$/
const MARKUP = /<\s*(?:[/!?]|[a-z])/i

const LINE_KINDS = Object.freeze({
  BULLET: 'bullet',
  NUMBERED: 'numbered',
  TABLE: 'table',
  TEXT: 'text',
})

function isInvisible(character) {
  const code = character.codePointAt(0)
  return INVISIBLE_RANGES.some(([from, to]) => code >= from && code <= to)
}

function withoutInvisible(text) {
  return [...text].filter((character) => !isInvisible(character)).join('')
}

export function cleanRichText(text) {
  return withoutInvisible(String(text ?? '').replace(/\r\n|\r/g, '\n'))
    .replace(/\t/g, ' ')
    .replace(/ +$/gm, '')
    .replace(/\n{3,}/g, '\n\n')
    .trim()
}

export function hasMarkup(text) {
  return MARKUP.test(String(text ?? ''))
}

function lineOf(line) {
  const bullet = line.match(BULLET_ITEM)
  if (bullet) return { kind: LINE_KINDS.BULLET, content: bullet[1].trim(), number: null }
  const numbered = line.match(NUMBERED_ITEM)
  if (numbered)
    return { kind: LINE_KINDS.NUMBERED, content: numbered[2].trim(), number: Number(numbered[1]) }
  return {
    kind: line.startsWith('|') ? LINE_KINDS.TABLE : LINE_KINDS.TEXT,
    content: line,
    number: null,
  }
}

function cellsOf(line) {
  return line
    .trim()
    .replace(/^\||\|$/g, '')
    .split('|')
    .map((cell) => cell.trim())
}

function padded(cells, width) {
  return [...cells, ...Array(Math.max(0, width - cells.length)).fill('')]
}

function tableOf(lines) {
  const hasHeader = lines.length > 1 && SEPARATOR_ROW.test(lines[1])
  const body = (hasHeader ? lines.slice(2) : lines).filter((line) => !SEPARATOR_ROW.test(line))
  const header = hasHeader ? cellsOf(lines[0]) : null
  const rows = body.map(cellsOf)
  const width = Math.max(header?.length ?? 0, ...rows.map((row) => row.length))
  return {
    type: RICH_TEXT_BLOCKS.TABLE,
    header: header ? padded(header, width) : null,
    rows: rows.map((row) => padded(row, width)),
  }
}

function blockOf(pending) {
  if (pending.kind === LINE_KINDS.BULLET)
    return { type: RICH_TEXT_BLOCKS.LIST, ordered: false, start: null, items: pending.lines }
  if (pending.kind === LINE_KINDS.NUMBERED)
    return {
      type: RICH_TEXT_BLOCKS.LIST,
      ordered: true,
      start: pending.start,
      items: pending.lines,
    }
  if (pending.kind === LINE_KINDS.TABLE) return tableOf(pending.lines)
  return { type: RICH_TEXT_BLOCKS.PARAGRAPH, text: pending.lines.join(' ') }
}

export function markdownBlocks(text) {
  const blocks = []
  let pending = null
  const flush = () => {
    if (pending?.lines.length) blocks.push(blockOf(pending))
    pending = null
  }
  cleanRichText(text)
    .split('\n')
    .forEach((rawLine) => {
      const line = rawLine.trim()
      if (!line) return flush()
      const heading = line.match(HEADING)
      if (heading) {
        flush()
        blocks.push({
          type: RICH_TEXT_BLOCKS.HEADING,
          level: heading[1].length,
          text: heading[2].trim(),
        })
        return undefined
      }
      const parsed = lineOf(line)
      if (parsed.kind !== pending?.kind) {
        flush()
        pending = { kind: parsed.kind, start: parsed.number, lines: [] }
      }
      pending.lines.push(parsed.content)
      return undefined
    })
  flush()
  return blocks
}
