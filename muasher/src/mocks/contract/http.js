import { http as mswHttp } from 'msw'
import { csrfMismatch, hasValidCsrf, requiresCsrf } from './csrf'
import { forcedFaultFor, forcedFaultResponse } from './faults'
import { decodeRequest, encodeResponse } from './wire'

const METHODS = ['get', 'post', 'put', 'patch', 'delete']

function withContract(method, resolver) {
  return async (info) => {
    if (requiresCsrf(method) && !hasValidCsrf(info.request)) return csrfMismatch()
    const fault = forcedFaultFor(method, info.request.url)
    if (fault) return forcedFaultResponse(fault, info.request)
    const request = await decodeRequest(info.request)
    return encodeResponse(await resolver({ ...info, request }))
  }
}

export const http = Object.fromEntries(
  METHODS.map((method) => [
    method,
    (path, resolver, options) => mswHttp[method](path, withContract(method, resolver), options),
  ]),
)
