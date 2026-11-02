import { formDataToWire, fromWire, toWire } from '../caseMapping'

function encodeBody(data) {
  if (data instanceof FormData) return formDataToWire(data)
  return toWire(data)
}

export function encodeRequest(config) {
  if (config.data !== undefined) config.data = encodeBody(config.data)
  if (config.params !== undefined) config.params = toWire(config.params)
  return config
}

export function decodeResponse(response) {
  response.data = fromWire(response.data)
  return response
}

export function decodeErrorResponse(error) {
  if (error.response) error.response.data = fromWire(error.response.data)
  throw error
}
