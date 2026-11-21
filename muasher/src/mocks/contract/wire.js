import { HttpResponse } from 'msw'
import {
  formDataFromWire,
  fromWire,
  toCamelKey,
  toWire,
  hasCanonicalValue,
} from '@/api/caseMapping'
import { fromCanonicalStatus } from '@/utils/canonicalStatus'

const JSON_TYPE = /application\/json/i
const FORM_TYPE = /multipart\/form-data/i

function decodeUrl(rawUrl) {
  const url = new URL(rawUrl)
  const decoded = new URLSearchParams()
  url.searchParams.forEach((value, key) => {
    const internalKey = toCamelKey(key)
    decoded.append(internalKey, hasCanonicalValue(internalKey) ? fromCanonicalStatus(value) : value)
  })
  url.search = decoded.toString()
  return url.toString()
}

function headersWithout(headers, names) {
  const copy = new Headers(headers)
  names.forEach((name) => copy.delete(name))
  return copy
}

async function decodeBody(request) {
  const type = request.headers.get('content-type') ?? ''
  if (JSON_TYPE.test(type)) {
    const text = await request.text()
    const body = text ? JSON.stringify(fromWire(JSON.parse(text))) : undefined
    return { body, headers: headersWithout(request.headers, ['content-length']) }
  }
  if (FORM_TYPE.test(type)) {
    const form = formDataFromWire(await request.formData())
    return {
      body: form,
      headers: headersWithout(request.headers, ['content-type', 'content-length']),
    }
  }
  return { body: await request.arrayBuffer(), headers: request.headers }
}

export async function decodeRequest(request) {
  const url = decodeUrl(request.url)
  if (request.method === 'GET' || request.method === 'HEAD') {
    return new Request(url, { method: request.method, headers: request.headers })
  }
  const { body, headers } = await decodeBody(request)
  return new Request(url, { method: request.method, headers, body })
}

export async function encodeResponse(response) {
  if (!(response instanceof Response)) return response
  if (!JSON_TYPE.test(response.headers.get('content-type') ?? '')) return response
  const body = await response.json()
  return HttpResponse.json(toWire(body), {
    status: response.status,
    headers: headersWithout(response.headers, ['content-type', 'content-length']),
  })
}
