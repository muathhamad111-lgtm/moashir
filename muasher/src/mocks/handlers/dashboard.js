import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan } from '../stores/sessionPermissions'
import { entityActionItems } from '../stores/entityBoard/entityActionItems'
import { staffActionItems } from '../stores/staffActionItems'
import { STAFF_BOARDS } from '../fixtures/staffBoards'
import { activeScenario } from '../stores/committee/committeeScenario'
import { DASHBOARD_BOARDS } from '@/constants/dashboardBoards'
import { reviewerActionItem } from './bankTransfers'

const LATENCY_MS = 320
const ENTITY_BOARD = 'entity'
const EXECUTIVE_BOARD = 'executive'
const EXECUTIVE_PERMISSION = 'dashboard.executive.view'
const COMMITTEE_SCENARIO = 'committee'
const GENERATED_AT = '2026-11-05T06:00:00+00:00'

function heldBoards(user) {
  const fromRoles = user.superAdmin ? ['system_admin'] : user.roles
  const held = new Set(fromRoles.filter((role) => DASHBOARD_BOARDS.includes(role)))
  if (user.roles.includes('assessor') && activeScenario() === COMMITTEE_SCENARIO)
    held.add('committee_assessor')
  if (user.superAdmin || sessionCan(EXECUTIVE_PERMISSION)) held.add(EXECUTIVE_BOARD)
  return DASHBOARD_BOARDS.filter((board) => held.has(board))
}

function actionItemsOf(user) {
  const entityItems = user.roles.includes(ENTITY_BOARD) ? entityActionItems(user) : []
  const reviewItem = user.roles.includes(ENTITY_BOARD) ? null : reviewerActionItem(user)
  return [
    ...(reviewItem ? [reviewItem] : []),
    ...staffActionItems(user.superAdmin ? ['system_admin'] : user.roles),
    ...entityItems,
  ]
}

export const dashboardHandlers = [
  http.get('/api/v1/dashboard/summary', async ({ request }) => {
    const requested = new URL(request.url).searchParams.get('board')
    if (requested === ENTITY_BOARD) return undefined
    await delay(LATENCY_MS)
    const user = currentSessionUser()
    if (!user) return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
    if (requested && !DASHBOARD_BOARDS.includes(requested))
      return errorResponse(422, 'validation_failed', 'The given data was invalid.', {
        board: ['The selected board is invalid.'],
      })
    const boards = heldBoards(user)
    const landing = boards.find((board) => board !== ENTITY_BOARD) ?? boards[0]
    const board = requested ?? landing
    if (!boards.includes(board) || !STAFF_BOARDS[board])
      return errorResponse(403, 'board.not_held', 'This dashboard is not available to you.')
    return envelope({
      board,
      landingBoard: landing,
      availableBoards: boards,
      ...STAFF_BOARDS[board],
      actionCenter: actionItemsOf(user),
      generatedAt: GENERATED_AT,
    })
  }),
]
