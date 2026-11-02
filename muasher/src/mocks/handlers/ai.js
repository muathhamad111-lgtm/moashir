import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { kbEntriesFixture } from '../fixtures/ai'
import { failsRandomly } from '../contract/faults'

const assistantActive = true

function envelope(data) {
  return HttpResponse.json({ data, meta: {}, errors: null })
}

const maybeFail = failsRandomly(0.08)

function normalize(text) {
  return String(text ?? '')
    .toLowerCase()
    .replace(/[إأآا]/g, 'ا')
    .replace(/ى/g, 'ي')
    .replace(/ة/g, 'ه')
    .trim()
}

function matchEntry(question) {
  const q = normalize(question)
  if (!q) return null
  let best = null
  let bestScore = 0
  for (const entry of kbEntriesFixture) {
    const keywords = [...(entry.keywords.ar ?? []), ...(entry.keywords.en ?? [])]
    let score = 0
    for (const keyword of keywords) {
      if (q.includes(normalize(keyword))) score += normalize(keyword).length
    }
    if (score > bestScore) {
      bestScore = score
      best = entry
    }
  }
  return best
}

export const aiHandlers = [
  http.get('/api/v1/ai/assistant', async () => {
    await delay(200)
    return envelope({
      active: assistantActive,
      provider: 'gpt',
      knowledgeBaseSize: kbEntriesFixture.length,
    })
  }),

  http.post('/api/v1/ai/query', async ({ request }) => {
    await delay(900)
    if (!assistantActive) {
      return HttpResponse.json(
        { message: 'The assistant is currently inactive', errors: {}, code: 423 },
        { status: 423 },
      )
    }
    if (maybeFail()) {
      return HttpResponse.json(
        { message: 'The assistant could not process the request', errors: {}, code: 500 },
        { status: 500 },
      )
    }
    const body = await request.json().catch(() => ({}))
    const match = matchEntry(body.question)
    if (!match) {
      return envelope({
        matched: false,
        entryId: null,
        answer: null,
      })
    }
    return envelope({
      matched: true,
      entryId: match.id,
      answer: match.answer,
    })
  }),
]
