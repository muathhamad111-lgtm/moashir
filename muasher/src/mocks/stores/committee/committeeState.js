const COLLECTIONS = [
  'seats',
  'assignments',
  'averages',
  'decisions',
  'discussions',
  'entries',
  'rankings',
  'regionApprovals',
]
const FIRST_ID = 7000

export const committeeState = Object.fromEntries(COLLECTIONS.map((name) => [name, []]))

let nextId = FIRST_ID

export function resetCommitteeState() {
  COLLECTIONS.forEach((name) => {
    committeeState[name] = []
  })
  nextId = FIRST_ID
}

export function newId() {
  nextId += 1
  return nextId
}

export function insert(collection, fields) {
  const row = { id: newId(), ...fields }
  committeeState[collection].push(row)
  return row
}

export function findSeat(id) {
  return committeeState.seats.find((seat) => seat.id === Number(id)) ?? null
}

export function seatsOfCycle(cycleId) {
  return committeeState.seats.filter((seat) => seat.cycleId === Number(cycleId))
}

export function activeSeatsOf(cycleId) {
  return seatsOfCycle(cycleId).filter((seat) => seat.status === 'active')
}

export function seatOfUser(cycleId, userId) {
  return (
    seatsOfCycle(cycleId).find((seat) => seat.userId === userId && seat.status !== 'removed') ??
    null
  )
}

export function seatsOfUser(userId) {
  return committeeState.seats.filter((seat) => seat.userId === userId)
}

export function findAssignment(id) {
  return committeeState.assignments.find((row) => row.id === Number(id)) ?? null
}

export function rowsOfRecord(assessmentId) {
  return committeeState.assignments.filter((row) => row.assessmentId === Number(assessmentId))
}

export function liveRowsOfRecord(assessmentId) {
  return rowsOfRecord(assessmentId).filter((row) => row.status !== 'voided')
}

export function rowsOfSeat(seatId) {
  return committeeState.assignments.filter((row) => row.seatId === seatId)
}

export function findAverage(id) {
  return committeeState.averages.find((average) => average.id === id) ?? null
}

export function findDiscussion(id) {
  return committeeState.discussions.find((discussion) => discussion.id === Number(id)) ?? null
}

export function discussionsOf(assessmentId) {
  return committeeState.discussions.filter((discussion) => discussion.assessmentId === assessmentId)
}

export function openDiscussionOf(assessmentId) {
  return discussionsOf(assessmentId).find((discussion) => discussion.status === 'open') ?? null
}

export function currentEntriesOf(discussionId) {
  return committeeState.entries.filter(
    (entry) => entry.discussionId === discussionId && entry.supersededAt === null,
  )
}

export function rankingOf(assessmentId) {
  return (
    committeeState.rankings.find((ranking) => ranking.assessmentId === Number(assessmentId)) ?? null
  )
}

export function rankingsOfCycle(cycleId) {
  return committeeState.rankings.filter((ranking) => ranking.cycleId === cycleId)
}

export function regionApprovalOf(cycleId, regionId) {
  return (
    committeeState.regionApprovals.find(
      (approval) =>
        approval.cycleId === cycleId &&
        approval.regionId === regionId &&
        approval.unapprovedAt === null,
    ) ?? null
  )
}

export function decisionsOf(assessmentId) {
  return committeeState.decisions.filter((decision) => decision.assessmentId === assessmentId)
}
