import { defineStore } from 'pinia'

export const useAssessmentResultHandoffStore = defineStore('assessmentResultHandoff', {
  state: () => ({
    result: null,
  }),
  actions: {
    hand(result) {
      this.result = result ?? null
    },
    consume(assessmentId) {
      const handed = this.result
      this.result = null
      return handed && handed.id === Number(assessmentId) ? handed : null
    },
  },
})
