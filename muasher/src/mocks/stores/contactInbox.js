import { CONTACT_INBOX_HANDLERS, contactInboxFixture } from '../fixtures/contactInbox'
import { MockRejection } from '../contract/rejection'

const OPEN = ['new', 'in-progress', 'replied']
const STATS = ['new', 'in-progress', 'replied', 'closed']
const BODY_MIN = 2
const BODY_MAX = 5000
const RIYADH_OFFSET = '+03:00'

const state = { messages: [], nextEntryId: 9100 }

export function resetContactInbox() {
  state.messages = structuredClone(contactInboxFixture)
  state.nextEntryId = 9100
}

resetContactInbox()

export function contactHandlers() {
  return CONTACT_INBOX_HANDLERS
}

function dayBoundary(value, end) {
  return value ? new Date(`${value}T${end ? '23:59:59' : '00:00:00'}${RIYADH_OFFSET}`) : null
}

function matchesAssignee(message, filter, viewerId) {
  if (!filter) return true
  if (filter === 'none') return !message.assignee
  if (filter === 'me') return message.assignee?.id === viewerId
  return message.assignee?.id === Number(filter)
}

function matchesSearch(message, term) {
  if (!term) return true
  const haystack = [
    message.reference,
    message.sender.name,
    message.sender.email,
    message.sender.organization,
  ]
    .filter(Boolean)
    .join(' ')
    .toLowerCase()
  return haystack.includes(term.toLowerCase())
}

function matches(message, query, viewerId) {
  const from = dayBoundary(query.get('filter[from]'), false)
  const to = dayBoundary(query.get('filter[to]'), true)
  const linked = query.get('filter[linked]')
  const type = query.get('filter[enquiryType]')
  const created = new Date(message.createdAt)
  if (type && message.enquiryType !== type) return false
  if (from && created < from) return false
  if (to && created > to) return false
  if (linked && String(message.isLinked) !== linked) return false
  if (!matchesAssignee(message, query.get('filter[assignee]'), viewerId)) return false
  return matchesSearch(message, query.get('search'))
}

function rowOf(message) {
  const row = { ...message, entryCount: message.entries.length }
  delete row.entries
  return row
}

export function inboxList(query, viewerId) {
  const status = query.get('filter[status]')
  const scoped = state.messages.filter((message) => matches(message, query, viewerId))
  const stats = Object.fromEntries(
    STATS.map((key) => [key, scoped.filter((message) => message.status === key).length]),
  )
  const rows = scoped
    .filter((message) => !status || message.status === status)
    .sort((left, right) => (left.lastActivityAt < right.lastActivityAt ? 1 : -1))
    .map(rowOf)
  return {
    rows,
    stats: {
      new: stats.new,
      inProgress: stats['in-progress'],
      replied: stats.replied,
      closed: stats.closed,
    },
  }
}

export function inboxMessage(id) {
  const message = state.messages.find((item) => item.id === id)
  if (!message) throw new MockRejection(404, 'not_found')
  return message
}

function validBody(body) {
  const text = typeof body === 'string' ? body.trim() : ''
  if (text.length < BODY_MIN || text.length > BODY_MAX)
    throw new MockRejection(422, 'validation_failed', {
      fields: {
        body: [
          {
            ar: 'يجب أن يتكون النص من 2 إلى 5000 حرف.',
            en: 'The text must be 2 to 5000 characters.',
          },
        ],
      },
    })
  return text
}

function touch(message, author) {
  message.lastActivityAt = new Date().toISOString()
  return author
}

function addEntry(message, kind, body, author) {
  state.nextEntryId += 1
  message.entries.push({
    id: state.nextEntryId,
    kind,
    body,
    author,
    createdAt: new Date().toISOString(),
  })
  touch(message, author)
}

export function assignMessage(id, assigneeId) {
  const message = inboxMessage(id)
  if (message.status === 'closed') throw new MockRejection(409, 'contact.message_closed')
  if (assigneeId === null) {
    message.assignee = null
    return message
  }
  const handler = CONTACT_INBOX_HANDLERS.find((person) => person.id === Number(assigneeId))
  if (!handler)
    throw new MockRejection(422, 'contact.assignee_not_eligible', {
      fields: {
        assigneeId: [
          {
            ar: 'لا يملك هذا الحساب صلاحية متابعة الرسائل.',
            en: 'This account cannot handle contact messages.',
          },
        ],
      },
    })
  message.assignee = { ...handler }
  if (message.status === 'new') message.status = 'in-progress'
  touch(message)
  return message
}

export function noteMessage(id, body, author) {
  const message = inboxMessage(id)
  addEntry(message, 'note', validBody(body), author)
  return message
}

export function replyMessage(id, body, author) {
  const message = inboxMessage(id)
  if (message.status === 'closed') throw new MockRejection(409, 'contact.message_closed')
  addEntry(message, 'reply', validBody(body), author)
  message.status = 'replied'
  message.repliedAt = message.lastActivityAt
  return message
}

export function closeMessage(id, closer) {
  const message = inboxMessage(id)
  if (!OPEN.includes(message.status)) throw new MockRejection(409, 'state.transition_illegal')
  Object.assign(message, { status: 'closed', closedAt: new Date().toISOString(), closedBy: closer })
  return message
}

export function reopenMessage(id) {
  const message = inboxMessage(id)
  if (message.status !== 'closed') throw new MockRejection(409, 'state.transition_illegal')
  Object.assign(message, { status: 'in-progress', closedAt: null, closedBy: null })
  touch(message)
  return message
}

export function inboxListAll() {
  return state.messages
}
