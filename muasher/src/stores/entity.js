import { defineStore } from 'pinia'
import { entitiesService } from '@/services/entitiesService'
import { useAuthStore } from '@/stores/auth'

export const useEntityStore = defineStore('entity', {
  state: () => ({
    profile: null,
    status: 'idle',
  }),
  getters: {
    profileCompleted: (state) => Boolean(state.profile?.profileCompleted),
    accountStatus: (state) => state.profile?.status ?? null,
    pendingEmailChange: (state) => state.profile?.pendingEmailChange ?? null,
  },
  actions: {
    async loadProfile({ force = false } = {}) {
      if (this.profile && !force) return this.profile
      this.status = 'loading'
      try {
        const { data } = await entitiesService.getMine()
        this.profile = data
        this.status = 'success'
        return data
      } catch (error) {
        this.status = 'error'
        throw error
      }
    },
    async updateProfile(payload) {
      const { data } = await entitiesService.updateMine(payload)
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
    },
  },
})
