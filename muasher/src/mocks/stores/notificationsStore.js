import { entityNotificationsFixture, notificationsFixture } from '../fixtures/notifications'
import { NOTIFICATION_TYPE_CATALOG } from '../fixtures/notificationTypes'

const MINUTE_MS = 60 * 1000
const ENTITY_ROLE = 'entity'
const UUID_PREFIX = '5e1f0c2a-0000-4000-8000-'
const UUID_TAIL_DIGITS = 12
const DEFAULT_CATEGORY = 'informational'

const CATEGORY_OF_TYPE = {
  info: 'informational',
  action: 'action_required',
  alert: 'alert',
  error: 'error',
}

const CATEGORY_OF_CODE = Object.fromEntries(
  NOTIFICATION_TYPE_CATALOG.map((type) => [type.code, type.category]),
)

let sequence = 0

function nextId() {
  sequence += 1
  return `${UUID_PREFIX}${String(sequence).padStart(UUID_TAIL_DIGITS, '0')}`
}

function categoryOf(code, type) {
  return CATEGORY_OF_CODE[code] ?? CATEGORY_OF_TYPE[type] ?? DEFAULT_CATEGORY
}

function stored({ code, type, recipientRole, actorId = null, title, body, link, path = null }) {
  return {
    id: nextId(),
    code,
    recipientRole,
    actorId,
    category: categoryOf(code, type),
    title,
    body,
    link: link ?? null,
    path,
    readAt: null,
    archivedAt: null,
    createdAt: new Date().toISOString(),
  }
}

function fromStaffFixture(item) {
  return {
    ...stored(item),
    readAt: item.read ? item.createdAt : null,
    createdAt: item.createdAt,
  }
}

function fromEntityFixture(item, now) {
  const createdAt = new Date(now - item.minutesAgo * MINUTE_MS).toISOString()
  return {
    ...stored({ ...item, recipientRole: ENTITY_ROLE }),
    readAt: item.isRead ? createdAt : null,
    createdAt,
  }
}

function seed(now = Date.now()) {
  sequence = 0
  return [
    ...notificationsFixture.map(fromStaffFixture),
    ...entityNotificationsFixture.map((item) => fromEntityFixture(item, now)),
  ]
}

export const notificationsStore = seed()

export function resetNotificationsStore(now = Date.now()) {
  notificationsStore.splice(0, notificationsStore.length, ...seed(now))
}

export function pushNotification(notification) {
  const created = stored(notification)
  notificationsStore.push(created)
  return created
}

export function visibleTo(viewer) {
  return (item) =>
    !viewer || item.actorId === null || item.actorId === undefined || item.actorId !== viewer.id
}

export function inboxOf(viewer) {
  if (!viewer) return []
  return notificationsStore.filter(
    (item) => viewer.roles.includes(item.recipientRole) && visibleTo(viewer)(item),
  )
}

export function findOwnNotification(viewer, id) {
  return inboxOf(viewer).find((item) => item.id === id) ?? null
}
