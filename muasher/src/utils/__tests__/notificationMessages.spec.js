import { describe, expect, it } from 'vitest'
import { smsSegments } from '@/utils/smsSegments'
import { mailMarkdownBlocks } from '@/utils/mailMarkdown'
import { channelPartsOf, emailPreviewParts } from '@/utils/notificationTextParts'
import { fillSamples } from '@/utils/notificationSamples'

describe('smsSegments', () => {
  it.each([
    ['', 0, 'gsm'],
    ['Your code is 1234', 1, 'gsm'],
    ['a'.repeat(161), 2, 'gsm'],
    ['ن'.repeat(70), 1, 'unicode'],
    ['ن'.repeat(71), 2, 'unicode'],
  ])('counts %s as %i message(s)', (text, segments, encoding) => {
    expect(smsSegments(text)).toMatchObject({ segments, encoding })
  })
})

describe('mailMarkdownBlocks', () => {
  it('reads bold, https links and list lines, and leaves everything else as text', () => {
    expect(
      mailMarkdownBlocks('مرحبًا **سارة**\n- [الدليل](https://a.sa)\n- [x](http://b.sa)'),
    ).toEqual([
      {
        type: 'paragraph',
        inlines: [
          { type: 'text', text: 'مرحبًا ' },
          { type: 'bold', text: 'سارة' },
        ],
      },
      {
        type: 'list',
        items: [
          [{ type: 'link', text: 'الدليل', href: 'https://a.sa' }],
          [{ type: 'text', text: '[x](http://b.sa)' }],
        ],
      },
    ])
  })
})

describe('notification text parts', () => {
  const text = {
    parts: {
      body: { channel: 'in_app' },
      title: { channel: 'in_app' },
      action: { channel: 'email' },
      emailBody: { channel: 'email' },
      emailSubject: { channel: 'email' },
      summary: { channel: 'email' },
    },
  }

  it('lists the parts of a channel subject first, and no SMS for a core notification', () => {
    expect(channelPartsOf(text, 'in_app')).toEqual(['title', 'body'])
    expect(channelPartsOf(text, 'email')).toEqual([
      'emailSubject',
      'emailBody',
      'action',
      'summary',
    ])
    expect(channelPartsOf(text, 'sms')).toEqual(['sms'])
    expect(channelPartsOf(text, 'sms', true)).toEqual([])
  })

  it('splits the e-mail into subject, body, button and other lines', () => {
    expect(emailPreviewParts(text)).toEqual({
      subject: 'emailSubject',
      body: 'emailBody',
      action: 'action',
      extras: ['summary'],
    })
  })

  it('fills known variables with samples and labels the others', () => {
    expect(fillSamples(':entity — :reason', 'ar', () => 'السبب')).toBe(
      'جمعية النور الخيرية — [السبب]',
    )
  })
})
