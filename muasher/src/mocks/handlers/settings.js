import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { generalFixture, kbFilesFixture } from '../fixtures/settings'
import { failsRandomly } from '../contract/faults'

const general = { ...generalFixture }
let kbFiles = kbFilesFixture.map((item) => ({ ...item }))

let kbSeed = 900

function envelope(data, meta = {}) {
  return HttpResponse.json({ data, meta, errors: null })
}

function errorResponse(message, status, fieldErrors = {}) {
  return HttpResponse.json({ message, errors: fieldErrors, code: status }, { status })
}

const maybeFail = failsRandomly(0.06)

export const platformSettingsHandlers = [
  http.get('/api/v1/settings/general', async () => {
    await delay(300)
    if (maybeFail(0.05)) return errorResponse('Failed to load general settings', 503)
    return envelope({ ...general })
  }),
  http.put('/api/v1/settings/general', async ({ request }) => {
    await delay(420)
    if (maybeFail()) return errorResponse('Could not save general settings', 500)
    const body = await request.json()
    Object.assign(general, body)
    return envelope({ ...general })
  }),
]

export const knowledgeBaseHandlers = [
  http.get('/api/v1/settings/ai/kb-files', async () => {
    await delay(320)
    if (maybeFail(0.05)) return errorResponse('Failed to load knowledge-base files', 503)
    return envelope(kbFiles.map((item) => ({ ...item })))
  }),
  http.post('/api/v1/settings/ai/kb-files', async ({ request }) => {
    await delay(600)
    if (maybeFail()) return errorResponse('Could not upload the file', 500)
    const body = await request.json()
    kbSeed += 1
    const created = {
      id: `kb-${kbSeed}`,
      name: body.name ?? 'ملف جديد.pdf',
      sizeKb: Number(body.sizeKb) || 0,
      status: 'processing',
      approved: false,
      uploadedAt: new Date().toISOString(),
    }
    kbFiles.push(created)
    return envelope({ ...created })
  }),
  http.patch('/api/v1/settings/ai/kb-files/:fileId/approved', async ({ request, params }) => {
    await delay(280)
    if (maybeFail(0.06)) return errorResponse('Could not update the file', 500)
    const file = kbFiles.find((item) => item.id === params.fileId)
    if (!file) return errorResponse('File not found', 404)
    const body = await request.json()
    file.approved = Boolean(body.approved)
    return envelope({ ...file })
  }),
  http.delete('/api/v1/settings/ai/kb-files/:fileId', async ({ params }) => {
    await delay(300)
    if (maybeFail(0.06)) return errorResponse('Could not delete the file', 500)
    kbFiles = kbFiles.filter((item) => item.id !== params.fileId)
    return envelope({ removed: true })
  }),
  http.post('/api/v1/settings/ai/retrain', async () => {
    await delay(1100)
    if (maybeFail(0.15)) return errorResponse('Retraining failed, please try again', 500)
    kbFiles = kbFiles.map((item) =>
      item.status === 'failed' ? { ...item } : { ...item, status: 'processed' },
    )
    return envelope({
      retrained: true,
      processedCount: kbFiles.filter((f) => f.status === 'processed').length,
    })
  }),
]
