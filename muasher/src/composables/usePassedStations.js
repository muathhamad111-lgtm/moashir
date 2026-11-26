import { submissionService } from '@/services/submissionService'

// Records the stations the entity moves past, so optional-only sub-criteria read complete there.
export function usePassedStations({ assessmentId, formState, onRejected }) {
  async function pass(mainId) {
    const passed = formState.form.value?.passedStations ?? []
    if (!formState.isEditable.value || passed.includes(mainId)) return
    formState.patch({ passedStations: [...passed, mainId] })
    try {
      const { data } = await submissionService.passStation(assessmentId.value, mainId)
      formState.patch({ passedStations: data })
    } catch (error) {
      formState.patch({ passedStations: passed })
      onRejected(error)
    }
  }

  return { pass }
}
