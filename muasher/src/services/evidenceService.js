import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toBundle, toEvidence, toEvidenceList } from './mappers/accredited'

const NOT_FOUND = 404
const { read, write } = domainRequests('evidence', 'evidence')

function mapData(request, mapper) {
  return request.then((envelope) => ({ ...envelope, data: mapper(envelope.data) }))
}

function uploadForm({ file, subCriterionId, attachmentKey, reason }) {
  const form = new FormData()
  form.append('file', file)
  form.append('subCriterionId', String(subCriterionId))
  if (attachmentKey) form.append('attachmentKey', attachmentKey)
  if (reason) form.append('reason', reason)
  return form
}

export const evidenceService = {
  list(assessmentId, params = {}) {
    const request = read(
      'list',
      () => apiClient.get(`/assessments/${assessmentId}/evidence`, { params }),
      params,
    )
    return mapData(request, toEvidenceList)
  },

  upload(assessmentId, upload) {
    const shape = {
      subCriterionId: upload.subCriterionId,
      attachmentKey: upload.attachmentKey ?? null,
      file: 'file',
    }
    const request = write(
      'upload',
      () => apiClient.post(`/assessments/${assessmentId}/evidence`, uploadForm(upload)),
      shape,
    )
    return mapData(request, toEvidence)
  },

  remove(assessmentId, evidenceId, payload = {}) {
    return write(
      'remove',
      () =>
        apiClient.delete(`/assessments/${assessmentId}/evidence/${evidenceId}`, { data: payload }),
      payload,
      [NOT_FOUND],
    )
  },

  requestDownload(assessmentId, evidenceId) {
    return write(
      'requestDownload',
      () => apiClient.post(`/assessments/${assessmentId}/evidence/${evidenceId}/download`),
      {},
      [NOT_FOUND],
    )
  },

  requestBundle(assessmentId) {
    return mapData(
      write(
        'requestBundle',
        () => apiClient.post(`/assessments/${assessmentId}/evidence/bundles`),
        {},
      ),
      toBundle,
    )
  },

  getBundle(assessmentId, bundleId) {
    const request = read(
      'getBundle',
      () => apiClient.get(`/assessments/${assessmentId}/evidence/bundles/${bundleId}`),
      null,
      [NOT_FOUND],
    )
    return mapData(request, toBundle)
  },

  requestBundleDownload(assessmentId, bundleId) {
    return write(
      'requestBundleDownload',
      () => apiClient.post(`/assessments/${assessmentId}/evidence/bundles/${bundleId}/download`),
      {},
      [NOT_FOUND],
    )
  },
}
