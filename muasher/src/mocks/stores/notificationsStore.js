import { notificationsFixture } from '../fixtures/notifications'

export const notificationsStore = notificationsFixture.map((item) => ({ ...item }))

let sequence = notificationsStore.reduce((highest, item) => Math.max(highest, item.id), 0)

export function pushNotification({ recipientRole, actorId = null, ...notification }) {
  sequence += 1
  const created = {
    id: sequence,
    type: 'info',
    channels: ['in-app'],
    read: false,
    createdAt: new Date().toISOString(),
    ...notification,
    recipientRole,
    actorId,
  }
  notificationsStore.push(created)
  return created
}

export function visibleTo(viewer) {
  return (item) =>
    !viewer || item.actorId === null || item.actorId === undefined || item.actorId !== viewer.id
}
