import { defineStore } from 'pinia'
import { AUTH_FLOW_INTENTS } from '@/constants/loginSteps'

export const useAuthFlowStore = defineStore('authFlow', {
  state: () => ({
    challenge: null,
    officialEmail: '',
    identifier: '',
    intent: AUTH_FLOW_INTENTS.LOGIN,
    pendingStep: null,
    redirect: null,
  }),
  getters: {
    hasChallenge: (state) => Boolean(state.challenge?.challengeId),
  },
  actions: {
    setChallenge({ challenge, officialEmail, intent, identifier }) {
      this.challenge = challenge
      if (officialEmail !== undefined) this.officialEmail = officialEmail
      if (intent !== undefined) this.intent = intent
      if (identifier !== undefined) this.identifier = identifier
    },
    setPendingStep(step, redirect = null) {
      this.pendingStep = step
      this.redirect = redirect
    },
    clear() {
      this.challenge = null
      this.officialEmail = ''
      this.identifier = ''
      this.intent = AUTH_FLOW_INTENTS.LOGIN
      this.pendingStep = null
      this.redirect = null
    },
  },
})
