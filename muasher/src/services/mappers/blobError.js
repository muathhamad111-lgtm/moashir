import { fromWire } from '@/api/caseMapping'
import { normalizeApiError } from '@/api/interceptors/errorNormalizer'

async function decodeBlobBody(error) {
  const body = error?.response?.data
  if (!(body instanceof Blob)) return
  const parsed = await body
    .text()
    .then(JSON.parse)
    .catch(() => null)
  if (!parsed) return
  error.response.data = fromWire(parsed)
  error.normalized = normalizeApiError(error)
}

export async function withJsonErrorBody(request) {
  try {
    return await request
  } catch (error) {
    await decodeBlobBody(error)
    throw error
  }
}
