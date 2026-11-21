import { describe, expect, it } from 'vitest'
import { cleanRichText, hasMarkup, markdownBlocks } from '@/utils/legal/markdownBlocks'

describe('markdownBlocks (same subset as the backend RichTextParser)', () => {
  it('reads headings, paragraphs, lists and tables', () => {
    const text = [
      'مسودة للمراجعة القانونية',
      'تكملة الفقرة.',
      '',
      '## 1. مقدمة',
      '',
      '- زوار المنصة؛',
      '- الجهات المسجلة.',
      '',
      '3. الحق في العلم',
      '4. الحق في الوصول',
      '',
      '| فئة البيانات | مدة الاحتفاظ |',
      '|---|---|',
      '| رسائل «تواصل معنا» | 24 شهراً |',
    ].join('\n')
    expect(markdownBlocks(text)).toEqual([
      { type: 'paragraph', text: 'مسودة للمراجعة القانونية تكملة الفقرة.' },
      { type: 'heading', level: 2, text: '1. مقدمة' },
      { type: 'list', ordered: false, start: null, items: ['زوار المنصة؛', 'الجهات المسجلة.'] },
      { type: 'list', ordered: true, start: 3, items: ['الحق في العلم', 'الحق في الوصول'] },
      {
        type: 'table',
        header: ['فئة البيانات', 'مدة الاحتفاظ'],
        rows: [['رسائل «تواصل معنا»', '24 شهراً']],
      },
    ])
  })

  it('pads short table rows and accepts a table without a header', () => {
    expect(markdownBlocks('| a | b |\n| c |')).toEqual([
      {
        type: 'table',
        header: null,
        rows: [
          ['a', 'b'],
          ['c', ''],
        ],
      },
    ])
  })

  it('removes bidi overrides and control characters like the server', () => {
    expect(cleanRichText('\u202Etext\u0007 \r\n\r\n\r\n\r\nnext')).toBe('text\n\nnext')
  })

  it('treats four hashes as text and trailing hashes as heading decoration', () => {
    expect(markdownBlocks('### Title ##\n#### Not a heading')).toEqual([
      { type: 'heading', level: 3, text: 'Title' },
      { type: 'paragraph', text: '#### Not a heading' },
    ])
  })

  it('detects HTML markup that the server refuses', () => {
    expect(hasMarkup('<p>text</p>')).toBe(true)
    expect(hasMarkup('a < b')).toBe(true)
    expect(hasMarkup('<!-- note -->')).toBe(true)
    expect(hasMarkup('الدرجة < 50 أو 3<5')).toBe(false)
  })

  it('returns no block for empty text', () => {
    expect(markdownBlocks('')).toEqual([])
    expect(markdownBlocks(null)).toEqual([])
  })
})
