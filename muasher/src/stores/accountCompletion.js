import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import { NATIONAL_ID_STATES } from '@/constants/identityChanges'
import { readStored, writeStored } from '@/utils/safeStorage'
import { fileAccessRefusalOf } from '@/utils/identity/fileAccessRefusal'

// «لاحقًا» keeps the modal closed for the rest of this sign-in only (operator decision, 2026-11-21): the key is the
// session's own absolute expiry, so the next sign-in asks again.
const DEFERRAL_KEY = 'muasher.accountCompletion.deferredFor'

export const useAccountCompletionStore = defineStore('accountCompletion', {
  state: () => ({
    dialogOpen: false,
    asksNationalId: false,
    refusedFileAccess: null,
    deferredFor: readStored(DEFERRAL_KEY),
  }),
  getters: {
    needsNationalId: () => useAuthStore().nationalIdStatus === NATIONAL_ID_STATES.MISSING,
    needsGender: () => useAuthStore().user?.genderMissing === true,
    isNeeded() {
      return this.needsNationalId || this.needsGender
    },
    sessionKey: () => {
      const user = useAuthStore().user
      return user ? `${user.id}:${user.sessionExpiresAt ?? ''}` : null
    },
    isDeferred(state) {
      return this.sessionKey !== null && state.deferredFor === this.sessionKey
    },
    shouldPrompt() {
      return this.isNeeded && !this.isDeferred && !this.dialogOpen
    },
    showsBanner() {
      return this.isNeeded && this.isDeferred && !this.dialogOpen
    },
  },
  actions: {
    open({ nationalId = false } = {}) {
      this.asksNationalId = nationalId || this.needsNationalId
      this.dialogOpen = true
    },
    defer() {
      if (this.isNeeded) {
        this.deferredFor = this.sessionKey
        writeStored(DEFERRAL_KEY, this.sessionKey)
      }
      this.close()
    },
    close() {
      this.dialogOpen = false
      this.asksNationalId = false
    },
    // Opens the file refusal dialog when the server refused a file for the missing or pending number;
    // the caller then shows no error of its own.
    refuseFileAccess(error) {
      const state = fileAccessRefusalOf(error)
      if (!state) return false
      useAuthStore().patchUser({ nationalIdStatus: state })
      this.refusedFileAccess = state
      return true
    },
    dismissFileRefusal() {
      this.refusedFileAccess = null
    },
    enterNationalIdForFiles() {
      this.refusedFileAccess = null
      this.open({ nationalId: true })
    },
  },
})
