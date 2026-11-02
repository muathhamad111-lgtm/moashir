import { defineStore } from 'pinia'
import { authService, UNAUTHENTICATED_STATUS } from '@/services/authService'
import { ROLES } from '@/constants/roles'
import { LOGIN_STEPS } from '@/constants/loginSteps'

let pendingSessionCheck = null

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    sessionChecked: false,
  }),
  getters: {
    isAuthenticated: (state) => state.user !== null,
    roles: (state) => state.user?.roles ?? [],
    permissions: (state) => state.user?.permissions ?? [],
    isSuperAdmin: (state) => state.user?.isSuperAdmin === true,
    committeeAppointments: (state) => state.user?.committeeAppointments ?? [],
    isEntity: (state) => (state.user?.roles ?? []).includes(ROLES.ENTITY),
    profileCompleted: (state) => Boolean(state.user?.profileCompleted),
    accountStatus: (state) => state.user?.status ?? null,
  },
  actions: {
    setUser(user) {
      this.user = user
      this.sessionChecked = true
    },
    clearSession() {
      this.user = null
      this.sessionChecked = true
    },
    patchUser(patch) {
      if (!this.user) return
      this.user = { ...this.user, ...patch }
    },
    ensureSession() {
      if (this.sessionChecked) return Promise.resolve(this.user)
      if (!pendingSessionCheck) {
        pendingSessionCheck = this.refreshSession().finally(() => {
          pendingSessionCheck = null
        })
      }
      return pendingSessionCheck
    },
    async refreshSession() {
      try {
        const { data } = await authService.session()
        this.setUser(data)
      } catch (error) {
        if (error?.response?.status === UNAUTHENTICATED_STATUS) this.clearSession()
        else this.user = null
      }
      return this.user
    },
    applyOutcome(outcome) {
      if (outcome.step === LOGIN_STEPS.AUTHENTICATED && outcome.user) this.setUser(outcome.user)
      return outcome
    },
    async register(payload) {
      const { data } = await authService.register(payload)
      return data
    },
    async loginWithPassword(credentials) {
      const { data } = await authService.loginWithPassword(credentials)
      return this.applyOutcome(data)
    },
    async requestLoginOtp(request) {
      const { data } = await authService.requestLoginOtp(request)
      return this.applyOutcome(data)
    },
    async verifyOtp(attempt) {
      const { data } = await authService.verifyOtp(attempt)
      return this.applyOutcome(data)
    },
    async resendOtp(request) {
      const { data } = await authService.resendOtp(request)
      return data
    },
    async challengeMfa(attempt) {
      const { data } = await authService.challengeMfa(attempt)
      return this.applyOutcome(data)
    },
    async startMfaEnrollment() {
      const { data } = await authService.startMfaEnrollment()
      return data
    },
    async confirmMfaEnrollment(attempt) {
      const { data } = await authService.confirmMfaEnrollment(attempt)
      return this.applyOutcome(data)
    },
    async logout() {
      const signedOut = await authService.logout().then(
        () => true,
        () => false,
      )
      this.clearSession()
      return signedOut
    },
  },
})
