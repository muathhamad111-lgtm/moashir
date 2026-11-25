import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan } from '../stores/sessionPermissions'
import { entityActionItems } from '../stores/entityBoard/entityActionItems'
import { staffActionItems } from '../stores/staffActionItems'
import { STAFF_BOARDS } from '../fixtures/staffBoards'
import { assessorActionItems, assessorBoard } from '../stores/accredited/assessorBoard'
import { activeScenario } from '../stores/committee/committeeScenario'
import { DASHBOARD_BOARDS } from '@/constants/dashboardBoards'
import { reviewerActionItem } from './bankTransfers'
import { allEntities, isDeleted, isErased } from '../stores/entitiesStore'
import { entitiesByLevel } from '../stores/entityLevels'
import { overviewErrors, overviewFor } from '../stores/dashboardOverview'
import { assessorOverviewFor } from '../stores/assessorOverview'

const LATENCY_MS = 320
const ENTITY_BOARD = 'entity'
const EXECUTIVE_PERMISSION = 'dashboard.executive.view'
const COMMITTEE_SCENARIO = 'committee'
const ASSESSOR_ROLE = 'assessor'
const GENERATED_AT = '2026-11-05T06:00:00+00:00'

function heldBoards(user) {
  const fromRoles = user.superAdmin ? ['system_admin'] : user.roles
  const held = new Set(fromRoles.filter((role) => DASHBOARD_BOARDS.includes(role)))
  if (user.roles.includes(ASSESSOR_ROLE) && activeScenario() === COMMITTEE_SCENARIO)
    held.add('committee_assessor')
  return DASHBOARD_BOARDS.filter((board) => held.has(board))
}

function boardContentOf(board, user) {
  return board === ASSESSOR_ROLE ? assessorBoard(user) : STAFF_BOARDS[board]
}

function levelCounts() {
  const counted = allEntities().filter((entity) => !isDeleted(entity) && !isErased(entity))
  return entitiesByLevel(counted)
}

function actionItemsOf(user) {
  const entityItems = user.roles.includes(ENTITY_BOARD) ? entityActionItems(user) : []
  const reviewItem = user.roles.includes(ENTITY_BOARD) ? null : reviewerActionItem(user)
  const assessorItems = user.roles.includes(ASSESSOR_ROLE) ? assessorActionItems(user) : []
  return [
    ...(reviewItem ? [reviewItem] : []),
    ...assessorItems,
    ...staffActionItems(user.superAdmin ? ['system_admin'] : user.roles),
    ...entityItems,
  ]
}

// The overview period fields the API reads.
function overviewQueryOf(params) {
  const fields = ['period', 'year', 'month', 'quarter', 'from', 'to']
  return Object.fromEntries(fields.map((field) => [field, params.get(field)]))
}

export const dashboardHandlers = [
  http.get('/api/v1/dashboard/action-center', async () => {
    await delay(LATENCY_MS)
    const user = currentSessionUser()
    if (!user) return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
    const items = actionItemsOf(user)
    return envelope(items, {
      total: items.reduce((sum, item) => sum + item.count, 0),
      generatedAt: new Date().toISOString(),
    })
  }),
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
    const content = boards.includes(board) ? boardContentOf(board, user) : null
    if (!content)
      return errorResponse(403, 'board.not_held', 'This dashboard is not available to you.')
    return envelope({
      board,
      landingBoard: landing,
      availableBoards: boards,
      ...content,
      generatedAt: GENERATED_AT,
    })
  }),
  http.get('/api/v1/dashboard/overview', async ({ request }) => {
    await delay(LATENCY_MS)
    const user = currentSessionUser()
    if (!user) return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
    if (!user.superAdmin && !sessionCan(EXECUTIVE_PERMISSION))
      return errorResponse(403, 'forbidden', 'This action is unauthorized.')
    const params = new URL(request.url).searchParams
    const query = overviewQueryOf(params)
    const errors = overviewErrors(query)
    if (Object.keys(errors).length)
      return errorResponse(422, 'validation_failed', Object.values(errors)[0][0], errors)
    return envelope(overviewFor(query, levelCounts()))
  }),
  http.get('/api/v1/dashboard/assessor-overview', async ({ request }) => {
    await delay(LATENCY_MS)
    const user = currentSessionUser()
    if (!user) return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
    if (!user.roles.includes(ASSESSOR_ROLE))
      return errorResponse(403, 'forbidden', 'This action is unauthorized.')
    const params = new URL(request.url).searchParams
    const query = overviewQueryOf(params)
    const errors = overviewErrors(query)
    if (Object.keys(errors).length)
      return errorResponse(422, 'validation_failed', Object.values(errors)[0][0], errors)
    return envelope(assessorOverviewFor(user, query))
  }),
]
