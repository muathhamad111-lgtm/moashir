import { HttpResponse } from 'msw'

export function envelope(data, meta = {}, init = {}) {
  return HttpResponse.json({ data, meta, errors: null }, init)
}

export function errorResponse(status, code, message, fieldErrors = {}) {
  return HttpResponse.json({ data: null, meta: {}, message, code, errors: fieldErrors }, { status })
}

export function noContent() {
  return new HttpResponse(null, { status: 204 })
}
