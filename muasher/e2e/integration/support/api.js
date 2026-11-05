const RETRY_LIMIT = 6
const DEFAULT_WAIT_S = 5

function browserCall({ method, path, body, upload }) {
  const cookie = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='))
  const headers = { Accept: 'application/json', 'Accept-Language': 'ar', 'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '' }
  let payload
  if (upload) {
    payload = new FormData()
    Object.entries(upload.fields).forEach(([key, value]) => payload.append(key, value))
    payload.append('file', new File([upload.content], upload.name, { type: upload.type }))
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    payload = JSON.stringify(body)
  }
  const init = payload === undefined ? { method, credentials: 'same-origin', headers } : { method, credentials: 'same-origin', headers, body: payload }
  return fetch(path, init).then(async (response) => {
    const text = await response.text()
    return { status: response.status, retryAfter: Number(response.headers.get('retry-after') ?? 0), body: text ? JSON.parse(text) : null }
  })
}

async function ensureCsrf(page) {
  await page.evaluate(() => fetch('/sanctum/csrf-cookie', { credentials: 'same-origin', headers: { Accept: 'application/json' } }))
}

export async function api(page, method, path, { body, upload } = {}) {
  await ensureCsrf(page)
  for (let attempt = 0; attempt < RETRY_LIMIT; attempt += 1) {
    const result = await page.evaluate(browserCall, { method, path: `/api/v1${path}`, body, upload })
    if (result.status !== 429) return result
    await new Promise((resolve) => setTimeout(resolve, (result.retryAfter || DEFAULT_WAIT_S) * 1000))
  }
  throw new Error(`${method} ${path} stayed rate limited.`)
}

export async function apiOk(page, method, path, options) {
  const result = await api(page, method, path, options)
  if (result.status >= 400) throw new Error(`${method} ${path} answered ${result.status} ${result.body?.code ?? ''}`)
  return result.body
}

export function scoreSheetOf(criteria, value) {
  const sheet = { subs: {}, points: {} }
  criteria.mains.forEach((main) =>
    main.subs.forEach((sub) => {
      if (sub.evaluation_type === 'guiding_points') sub.points.forEach((point) => (sheet.points[point.id] = value))
      else sheet.subs[sub.id] = value
    }),
  )
  return sheet
}

export const EVIDENCE_PDF = '%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n% Moashir committee integration evidence\n%%EOF\n'
