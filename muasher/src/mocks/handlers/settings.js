import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import {
  generalFixture,
  otpFixture,
  certificatesFixture,
  kbFilesFixture,
  notificationTypesFixture,
  notificationAuditFixture,
} from '../fixtures/settings'
import { failsRandomly } from '../contract/faults'

const general = { ...generalFixture }
const otp = { ...otpFixture }
let certificates = certificatesFixture.map((item) => ({ ...item }))
let kbFiles = kbFilesFixture.map((item) => ({ ...item }))
let notificationTypes = notificationTypesFixture.map((item) => ({ ...item }))

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

  http.get('/api/v1/settings/otp', async () => {
    await delay(260)
    if (maybeFail(0.05)) return errorResponse('Failed to load OTP settings', 503)
    return envelope({ ...otp })
  }),
  http.put('/api/v1/settings/otp', async ({ request }) => {
    await delay(360)
    if (maybeFail()) return errorResponse('Could not save OTP settings', 500)
    const body = await request.json()
    const next = { ...otp, ...body }
    if (!next.emailOtp && !next.smsOtp && !next.emailPassword) {
      return errorResponse('At least one login mode must stay enabled', 422, {
        emailPassword: ['At least one login mode must stay enabled.'],
      })
    }
    Object.assign(otp, next)
    return envelope({ ...otp })
  }),
]

export const certificateSettingsHandlers = [
  http.get('/api/v1/settings/certificates', async () => {
    await delay(320)
    if (maybeFail(0.05)) return errorResponse('Failed to load certificate settings', 503)
    return envelope(certificates.map((item) => ({ ...item })))
  }),
  http.put('/api/v1/settings/certificates/:kind', async ({ request, params }) => {
    await delay(420)
    if (maybeFail()) return errorResponse('Could not save the certificate template', 500)
    const body = await request.json()
    const found = certificates.find((item) => item.kind === params.kind)
    if (!found) return errorResponse('Certificate template not found', 404)
    Object.assign(found, body, { kind: params.kind })
    certificates = certificates.map((item) => ({ ...item }))
    return envelope({ ...found })
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

export const notificationSettingsHandlers = [
  http.get('/api/v1/settings/notifications/types', async ({ request }) => {
    await delay(340)
    if (maybeFail(0.05)) return errorResponse('Failed to load notification settings', 503)
    const url = new URL(request.url)
    const category = url.searchParams.get('filter[category]')
    const search = (url.searchParams.get('search') ?? '').trim().toLowerCase()
    let items = notificationTypes.map((item) => ({ ...item }))
    if (category) items = items.filter((item) => item.category === category)
    if (search) {
      items = items.filter(
        (item) =>
          item.code.toLowerCase().includes(search) ||
          item.label.ar.toLowerCase().includes(search) ||
          item.label.en.toLowerCase().includes(search),
      )
    }
    return envelope(items)
  }),
  http.put('/api/v1/settings/notifications/types/:code', async ({ request, params }) => {
    await delay(400)
    if (maybeFail()) return errorResponse('Could not save the notification type', 500)
    const found = notificationTypes.find((item) => item.code === params.code)
    if (!found) return errorResponse('Notification type not found', 404)
    const body = await request.json()
    Object.assign(found, body, { code: params.code })
    notificationTypes = notificationTypes.map((item) => ({ ...item }))
    return envelope({ ...found })
  }),
  http.get('/api/v1/settings/notifications/audit', async ({ request }) => {
    await delay(360)
    if (maybeFail(0.06)) return errorResponse('Failed to load the audit log', 503)
    const url = new URL(request.url)
    const code = url.searchParams.get('filter[code]')
    let items = notificationAuditFixture.map((item) => ({ ...item }))
    if (code) items = items.filter((item) => item.code === code)
    return envelope(items)
  }),
]
