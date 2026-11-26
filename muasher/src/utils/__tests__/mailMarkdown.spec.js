import { describe, expect, it } from 'vitest'
import { mailMarkdownBlocks } from '@/utils/mailMarkdown'

const VALUES = { entity: '[حدّث بياناتك](https://evil.example/login) **عاجل**' }
const fill = (text) => text.replace(/:([a-z]+)/g, (match, name) => VALUES[name] ?? match)

describe('mailMarkdownBlocks', () => {
  it('renders the administrator’s bold text, links and list lines', () => {
    expect(mailMarkdownBlocks('**مرحبًا**\n- راجع [الدليل](https://example.sa)')).toEqual([
      { type: 'paragraph', inlines: [{ type: 'bold', text: 'مرحبًا' }] },
      {
        type: 'list',
        items: [
          [
            { type: 'text', text: 'راجع ' },
            { type: 'link', text: 'الدليل', href: 'https://example.sa' },
          ],
        ],
      },
    ])
  })

  it('fills a value in after parsing, so it stays literal text and never a link or bold', () => {
    expect(mailMarkdownBlocks('سجّلت :entity، **راجع :entity**', fill)).toEqual([
      {
        type: 'paragraph',
        inlines: [
          { type: 'text', text: `سجّلت ${VALUES.entity}، ` },
          { type: 'bold', text: `راجع ${VALUES.entity}` },
        ],
      },
    ])
  })

  it('never puts a value into a link address', () => {
    const [paragraph] = mailMarkdownBlocks('[دليل :entity](https://example.sa/:entity)', fill)
    expect(paragraph.inlines[0].href).toBe('https://example.sa/:entity')
  })
})
