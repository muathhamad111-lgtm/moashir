import { defineStore } from 'pinia'
import { entitiesService } from '@/services/entitiesService'
import { profileChangeRequestsService } from '@/services/profileChangeRequestsService'
import { useAuthStore } from '@/stores/auth'
import { logger } from '@/utils/logger'

export const useEntityStore = defineStore('entity', {
  state: () => ({
    profile: null,
    status: 'idle',
    pendingChange: null,
  }),
  getters: {
    profileCompleted: (state) => Boolean(state.profile?.profileCompleted),
    accountStatus: (state) => state.profile?.status ?? null,
    pendingEmailChange: (state) => state.profile?.pendingEmailChange ?? null,
    hasPendingChange: (state) => Boolean(state.profile?.pendingProfileChange),
  },
  actions: {
    async loadProfile({ force = false } = {}) {
      if (this.profile && !force) return this.profile
      this.status = 'loading'
      try {
        const { data } = await entitiesService.getMine()
        this.profile = data
        await this.syncPendingChangeQuietly()
        this.status = 'success'
        return data
      } catch (error) {
        this.status = 'error'
        throw error
      }
    },
    async saveSection(section, payload) {
      const { data, meta } = await entitiesService.saveProfileSection(section, payload)
      await this.applyProposal(data)
      return { data, meta }
    },
    /** Applies the own profile returned by an edit that went to approval, with its pending request. */
    async applyProposal(data) {
      this.applyProfile(data)
      await this.syncPendingChangeQuietly()
    },
    async syncPendingChangeQuietly() {
      try {
        await this.syncPendingChange()
      } catch (error) {
        this.pendingChange = null
        logger.error(error, { action: 'entities.profile.pendingChange.load' })
      }
    },
    /** Loads the details of the entity's pending change request, or clears them when there is none. */
    async syncPendingChange() {
      if (!this.profile?.pendingProfileChange) {
        this.pendingChange = null
        return null
      }
      const { data } = await profileChangeRequestsService.ownPending()
      this.pendingChange = data
      return data
    },
    async cancelPendingChange() {
      const { data } = await profileChangeRequestsService.cancelOwn()
      this.applyProfile(data)
      this.pendingChange = null
      return data
    },
    async cancelEmailChange() {
      const { data } = await entitiesService.cancelEmailChange()
      this.applyProfile(data)
      return data
    },
    applyProfile(data) {
      this.profile = data
      const auth = useAuthStore()
      auth.patchUser({
        profileCompleted: data.profileCompleted,
        status: data.status,
        email: data.officialEmail,
        entity: auth.user?.entity
          ? { ...auth.user.entity, profileCompleted: data.profileCompleted }
          : null,
      })
    },
    reset() {
      this.profile = null
      this.status = 'idle'
      this.pendingChange = null
    },
  },
})
