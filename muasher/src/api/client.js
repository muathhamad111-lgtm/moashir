import axios from 'axios'
import { API_BASE_URL } from './config'
import { XSRF_COOKIE_NAME, XSRF_HEADER_NAME } from './csrf'
import { applyAcceptLanguage } from './interceptors/acceptLanguage'
import { ensureCsrfBeforeWrite, retryOnceAfterCsrfMismatch } from './interceptors/csrf'
import { reportSessionLoss } from './interceptors/sessionExpiry'
import { attachNormalizedError } from './interceptors/errorNormalizer'
import { decodeErrorResponse, decodeResponse, encodeRequest } from './interceptors/contractCase'

export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  allowAbsoluteUrls: false,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: XSRF_COOKIE_NAME,
  xsrfHeaderName: XSRF_HEADER_NAME,
  headers: { Accept: 'application/json' },
})

apiClient.interceptors.request.use(encodeRequest)
apiClient.interceptors.request.use(applyAcceptLanguage)
apiClient.interceptors.request.use(ensureCsrfBeforeWrite)

apiClient.interceptors.response.use(decodeResponse, decodeErrorResponse)
apiClient.interceptors.response.use(undefined, retryOnceAfterCsrfMismatch(apiClient))
apiClient.interceptors.response.use(undefined, reportSessionLoss)
apiClient.interceptors.response.use(undefined, attachNormalizedError)
