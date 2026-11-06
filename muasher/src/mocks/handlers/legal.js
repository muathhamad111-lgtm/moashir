import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { requestLocale } from '../contract/locale'
import { accessibilityPageFixture, legalTextsFixture } from '../fixtures/legalTexts'
import { sessionPublication } from '../stores/legalVersionsStore'
import { markdownBlocks } from '@/utils/legal/markdownBlocks'

const MESSAGES = {
  'legal.not_published': {
    ar: 'لا يوجد إصدار منشور من هذا النص.',
    en: 'No published version of this text exists.',
  },
  not_found: { ar: 'غير موجود.', en: 'Not found.' },
}

function publishedDocument(kind) {
  const published = sessionPublication(kind)
  if (!published) return legalTextsFixture[kind] ?? null
  return {
    id: published.id,
    kind,
    version: published.version,
    effectiveFrom: published.effectiveFrom,
    publishedAt: published.publishedAt,
    title: published.title,
    body: { ar: markdownBlocks(published.body.ar), en: markdownBlocks(published.body.en) },
  }
}

function missing(request, code) {
  const message = MESSAGES[code]
  return HttpResponse.json(
    {
      data: null,
      meta: {},
      message: message[requestLocale(request)] ?? message.ar,
      code,
      errors: {},
    },
    { status: 404 },
  )
}

export const legalHandlers = [
  http.get('/api/v1/content/legal/:kind', async ({ params, request }) => {
    await delay(180)
    const document = publishedDocument(params.kind)
    if (!document) return missing(request, 'not_found')
    return envelope(document)
  }),

  http.get('/api/v1/content/pages/accessibility', async () => {
    await delay(180)
    return envelope(accessibilityPageFixture)
  }),
]
