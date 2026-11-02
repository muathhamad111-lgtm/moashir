import { defineStore } from 'pinia'
import { AUTH_FLOW_INTENTS } from '@/constants/loginSteps'

export const useAuthFlowStore = defineStore('authFlow', {
  state: () => ({
    challenge: null,
    officialEmail: '',
    intent: AUTH_FLOW_INTENTS.LOGIN,
    pendingStep: null,
    redirect: null,
  }),
  getters: {
    hasChallenge: (state) => Boolean(state.challenge?.challengeId),
  },
  actions: {
    setChallenge({ challenge, officialEmail, intent }) {
      this.challenge = challenge
      if (officialEmail !== undefined) this.officialEmail = officialEmail
      if (intent !== undefined) this.intent = intent
    },
    setPendingStep(step, redirect = null) {
      this.pendingStep = step
      this.redirect = redirect
    },
    clear() {
      this.challenge = null
      this.officialEmail = ''
      this.intent = AUTH_FLOW_INTENTS.LOGIN
      this.pendingStep = null
      this.redirect = null
    },
  },
})
