import { defineStore } from 'pinia'
import { notificationsService } from '@/services/notificationsService'
import { contactMessagesService } from '@/services/contactMessagesService'

const SINGLE_ROW = { perPage: 1 }

export const usePortalCountsStore = defineStore('portalCounts', {
  state: () => ({ unreadNotifications: 0, unreadReplies: 0 }),
  actions: {
    async refreshNotifications() {
      const envelope = await notificationsService.unreadCount().catch(() => null)
      if (envelope) this.unreadNotifications = Number(envelope.data?.count ?? 0)
    },
    async refreshReplies() {
      const envelope = await contactMessagesService.listMine(SINGLE_ROW).catch(() => null)
      if (envelope) this.unreadReplies = Number(envelope.meta?.unreadReplies ?? 0)
    },
    refreshAll() {
      return Promise.all([this.refreshNotifications(), this.refreshReplies()])
    },
    setUnreadNotifications(count) {
      this.unreadNotifications = Math.max(0, Number(count) || 0)
    },
    setUnreadReplies(count) {
      this.unreadReplies = Math.max(0, Number(count) || 0)
    },
    notificationRead() {
      this.unreadNotifications = Math.max(0, this.unreadNotifications - 1)
    },
    reset() {
      this.unreadNotifications = 0
      this.unreadReplies = 0
    },
  },
})
