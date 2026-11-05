import { apiClient } from '@/api/client'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'
import { toActionItems, toDashboardSummary, toEntityBoard } from './mappers/dashboard'

const ENTITY_BOARD = 'entity'
const BOARD_REJECTIONS = [403, 422]

function summaryRequest(board, expectedStatuses = []) {
  const query = board ? { board } : {}
  return serviceRequest(
    'dashboard.getSummary',
    () => apiClient.get('/dashboard/summary', { params: query }),
    { entity: 'dashboard', paramsShape: payloadShape(query) },
    { expectedStatuses },
  )
}

export const dashboardService = {
  getBoard(board = null) {
    return summaryRequest(board, BOARD_REJECTIONS).then((envelope) => ({
      ...envelope,
      data: toDashboardSummary(envelope.data),
    }))
  },

  getEntityBoard() {
    return summaryRequest(ENTITY_BOARD).then((envelope) => ({
      ...envelope,
      data: toEntityBoard(envelope.data),
    }))
  },

  getActionCenter() {
    return serviceRequest(
      'dashboard.getActionCenter',
      () => apiClient.get('/dashboard/action-center'),
      { entity: 'dashboard' },
    ).then((envelope) => ({ ...envelope, data: toActionItems(envelope.data) }))
  },
}
