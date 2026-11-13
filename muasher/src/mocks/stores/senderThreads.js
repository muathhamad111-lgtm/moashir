import { MockRejection } from '../contract/rejection'
import { SENDER_THREADS_FIXTURE } from '../fixtures/contactMessages'

const MINUTE_MS = 60 * 1000
const ENTITY_ROLE = 'entity'
const PREVIEW_LENGTH = 160
const BODY_MIN = 2
const BODY_MAX = 5000
const OPEN_STATUSES = ['new', 'in_progress', 'replied']

const state = { threads: null, entrySequence: 1 }

function minutesAgo(now, minutes) {
  return minutes === null ? null : new Date(now - minutes * MINUTE_MS).toISOString()
}

function entryOf(fixture, now) {
  const entry = {
    id: state.entrySequence,
    kind: fixture.author === 'team' ? 'reply' : 'message',
    body: fixture.body,
    author: fixture.author,
    createdAt: minutesAgo(now, fixture.minutesAgo),
  }
  state.entrySequence += 1
  return entry
}

function threadOf(fixture, now) {
  const entries = fixture.entries.map((entry) => entryOf(entry, now))
  return {
    id: fixture.id,
    reference: fixture.reference,
    status: fixture.status,
    enquiryType: fixture.enquiryType,
    createdAt: minutesAgo(now, fixture.createdMinutesAgo),
    repliedAt: minutesAgo(now, fixture.repliedMinutesAgo),
    closedAt: minutesAgo(now, fixture.closedMinutesAgo),
    hasUnreadReply: fixture.hasUnreadReply,
    entries,
  }
}

function threads(now = Date.now()) {
  if (!state.threads) state.threads = SENDER_THREADS_FIXTURE.map((item) => threadOf(item, now))
  return state.threads
}

function isSender(user) {
  return Boolean(user?.roles?.includes(ENTITY_ROLE))
}

function lastEntry(thread) {
  return thread.entries[thread.entries.length - 1] ?? null
}

function card(thread) {
  const last = lastEntry(thread)
  return {
    id: thread.id,
    reference: thread.reference,
    status: thread.status,
    enquiryType: thread.enquiryType,
    canReply: OPEN_STATUSES.includes(thread.status),
    createdAt: thread.createdAt,
    lastActivityAt: last?.createdAt ?? thread.createdAt,
    repliedAt: thread.repliedAt,
    closedAt: thread.closedAt,
    hasUnreadReply: thread.hasUnreadReply,
    preview: (last?.body ?? '').replace(/\s+/g, ' ').trim().slice(0, PREVIEW_LENGTH),
    lastEntryAuthor: last?.author ?? null,
  }
}

function detail(thread) {
  return { ...card(thread), entries: thread.entries.map((entry) => ({ ...entry })) }
}

function byActivity(first, second) {
  return new Date(second.lastActivityAt) - new Date(first.lastActivityAt)
}

function ownThread(user, id) {
  const thread = isSender(user) ? threads().find((candidate) => candidate.id === id) : null
  if (!thread) throw new MockRejection(404, 'not_found')
  return thread
}

export function senderThreadCards(user) {
  return isSender(user) ? threads().map(card).sort(byActivity) : []
}

export function senderThreadDetail(user, id) {
  return detail(ownThread(user, id))
}

export function markSenderThreadSeen(user, id) {
  const thread = ownThread(user, id)
  thread.hasUnreadReply = false
  return detail(thread)
}

export function addSenderFollowUp(user, id, body) {
  const thread = ownThread(user, id)
  if (!OPEN_STATUSES.includes(thread.status)) throw new MockRejection(409, 'contact.message_closed')
  const text = typeof body === 'string' ? body.trim() : ''
  if (text.length < BODY_MIN || text.length > BODY_MAX)
    throw new MockRejection(422, 'validation_failed', {
      fields: {
        body:
          text.length < BODY_MIN
            ? { key: 'min', params: { min: BODY_MIN } }
            : { key: 'max', params: { max: BODY_MAX } },
      },
    })
  thread.entries.push(entryOf({ author: 'sender', minutesAgo: 0, body: text }, Date.now()))
  thread.status = thread.status === 'new' ? 'new' : 'in_progress'
  thread.hasUnreadReply = false
  return detail(thread)
}

export function openSenderThread(user, { reference, enquiryType, message }, now = Date.now()) {
  if (!isSender(user)) return null
  const thread = {
    id: crypto.randomUUID(),
    reference,
    status: 'new',
    enquiryType,
    createdAt: new Date(now).toISOString(),
    repliedAt: null,
    closedAt: null,
    hasUnreadReply: false,
    entries: [entryOf({ author: 'sender', minutesAgo: 0, body: message.trim() }, now)],
  }
  threads(now).unshift(thread)
  return thread.id
}

export function resetSenderThreads() {
  state.threads = null
  state.entrySequence = 1
}
