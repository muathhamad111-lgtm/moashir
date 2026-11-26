import { apiClient } from '@/api/client'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'

const DOMAIN_REJECTION = [409, 422]

function send(action, execute, context, options) {
  return serviceRequest(`criteria.${action}`, execute, context, options)
}

function write(action, execute, entity, payload) {
  return send(
    action,
    execute,
    { entity, payloadShape: payloadShape(payload ?? {}) },
    { expectedStatuses: DOMAIN_REJECTION },
  )
}

export const criteriaService = {
  listEditEvents(params = {}) {
    return send('listEditEvents', () => apiClient.get('/criteria/edit-events', { params }), {
      entity: 'criteria-edit-event',
      paramsShape: payloadShape(params),
    })
  },
  listVersions(params = {}) {
    return send('listVersions', () => apiClient.get('/criteria/versions', { params }), {
      entity: 'criteria-version',
      paramsShape: payloadShape(params),
    })
  },
  getVersion(versionId) {
    return send('getVersion', () => apiClient.get(`/criteria/versions/${versionId}`), {
      entity: 'criteria-version',
    })
  },
  getTree(versionId) {
    return send('getTree', () => apiClient.get(`/criteria/versions/${versionId}/tree`), {
      entity: 'criteria-version',
    })
  },
  createDraft(payload = {}) {
    return write(
      'createDraft',
      () => apiClient.post('/criteria/versions', payload),
      'criteria-version',
      payload,
    )
  },
  discardDraft(versionId) {
    return write(
      'discardDraft',
      () => apiClient.delete(`/criteria/versions/${versionId}`),
      'criteria-version',
    )
  },
  checkVersion(versionId) {
    return send('checkVersion', () => apiClient.get(`/criteria/versions/${versionId}/check`), {
      entity: 'criteria-version',
    })
  },
  saveSubWeights(mainId, weights) {
    const payload = { weights }
    return write(
      'saveSubWeights',
      () => apiClient.put(`/criteria/mains/${mainId}/subs/weights`, payload),
      'sub-criterion',
      payload,
    )
  },
  savePointWeights(subId, weights) {
    const payload = { weights }
    return write(
      'savePointWeights',
      () => apiClient.put(`/criteria/subs/${subId}/points/weights`, payload),
      'guiding-point',
      payload,
    )
  },
  applyPointScoringType(subId, scoringType) {
    const payload = { scoringType }
    return write(
      'applyPointScoringType',
      () => apiClient.put(`/criteria/subs/${subId}/points/scoring-type`, payload),
      'sub-criterion',
      payload,
    )
  },
  previewPublication(versionId) {
    return send(
      'previewPublication',
      () => apiClient.get(`/criteria/versions/${versionId}/changes`),
      { entity: 'criteria-version' },
      { expectedStatuses: DOMAIN_REJECTION },
    )
  },
  publishVersion(versionId, { scope = null, reason = null } = {}) {
    const payload = { confirm: true, scope, reason }
    return write(
      'publishVersion',
      () => apiClient.post(`/criteria/versions/${versionId}/publish`, payload),
      'criteria-version',
      payload,
    )
  },
  correctTexts(versionId, payload) {
    return write(
      'correctTexts',
      () => apiClient.patch(`/criteria/versions/${versionId}/labels`, payload),
      'criteria-version',
      payload,
    )
  },

  listMains(params = {}) {
    return send('listMains', () => apiClient.get('/criteria/mains', { params }), {
      entity: 'main-criterion',
      paramsShape: payloadShape(params),
    })
  },
  getMain(mainId) {
    return send('getMain', () => apiClient.get(`/criteria/mains/${mainId}`), {
      entity: 'main-criterion',
    })
  },
  createMain(payload) {
    return write(
      'createMain',
      () => apiClient.post('/criteria/mains', payload),
      'main-criterion',
      payload,
    )
  },
  updateMain(mainId, payload) {
    return write(
      'updateMain',
      () => apiClient.put(`/criteria/mains/${mainId}`, payload),
      'main-criterion',
      payload,
    )
  },
  toggleMainActive(mainId, active) {
    return write(
      'toggleMainActive',
      () => apiClient.patch(`/criteria/mains/${mainId}/active`, { active }),
      'main-criterion',
    )
  },
  reorderMains(orderedIds) {
    return write(
      'reorderMains',
      () => apiClient.post('/criteria/mains/reorder', { orderedIds }),
      'main-criterion',
      { orderedIds },
    )
  },
  removeMain(mainId) {
    return write(
      'removeMain',
      () => apiClient.delete(`/criteria/mains/${mainId}`),
      'main-criterion',
    )
  },

  getSub(subId) {
    return send('getSub', () => apiClient.get(`/criteria/subs/${subId}`), {
      entity: 'sub-criterion',
    })
  },
  createSub(payload) {
    return write(
      'createSub',
      () => apiClient.post('/criteria/subs', payload),
      'sub-criterion',
      payload,
    )
  },
  updateSub(subId, payload) {
    return write(
      'updateSub',
      () => apiClient.put(`/criteria/subs/${subId}`, payload),
      'sub-criterion',
      payload,
    )
  },
  switchEvaluationType(subId, evaluationType) {
    const payload = { evaluationType }
    return write(
      'switchEvaluationType',
      () => apiClient.patch(`/criteria/subs/${subId}/evaluation-type`, payload),
      'sub-criterion',
      payload,
    )
  },
  toggleSubActive(subId, active) {
    return write(
      'toggleSubActive',
      () => apiClient.patch(`/criteria/subs/${subId}/active`, { active }),
      'sub-criterion',
    )
  },
  reorderSubs(mainId, orderedIds) {
    return write(
      'reorderSubs',
      () => apiClient.post(`/criteria/mains/${mainId}/subs/reorder`, { orderedIds }),
      'sub-criterion',
      { orderedIds },
    )
  },
  removeSub(subId) {
    return write('removeSub', () => apiClient.delete(`/criteria/subs/${subId}`), 'sub-criterion')
  },

  createPoint(subId, payload) {
    return write(
      'createPoint',
      () => apiClient.post(`/criteria/subs/${subId}/points`, payload),
      'guiding-point',
      payload,
    )
  },
  updatePoint(pointId, payload) {
    return write(
      'updatePoint',
      () => apiClient.put(`/criteria/points/${pointId}`, payload),
      'guiding-point',
      payload,
    )
  },
  togglePointActive(pointId, active) {
    return write(
      'togglePointActive',
      () => apiClient.patch(`/criteria/points/${pointId}/active`, { active }),
      'guiding-point',
    )
  },
  reorderPoints(subId, orderedIds) {
    return write(
      'reorderPoints',
      () => apiClient.post(`/criteria/subs/${subId}/points/reorder`, { orderedIds }),
      'guiding-point',
      { orderedIds },
    )
  },
  removePoint(pointId) {
    return write(
      'removePoint',
      () => apiClient.delete(`/criteria/points/${pointId}`),
      'guiding-point',
    )
  },
}
