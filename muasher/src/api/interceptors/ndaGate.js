import { requestNdaAcceptance } from '../ndaEvents'

export const NDA_REQUIRED_CODE = 'nda.acceptance_required'

const FORBIDDEN = 403

export function isNdaRequired(error) {
  return error.response?.status === FORBIDDEN && error.response?.data?.code === NDA_REQUIRED_CODE
}

// Only a read is replayed after the user accepts. A write that was refused (a submission, an approval) is not
// sent again on its own: the user repeats the action, since it may not be safe to run twice.
function isReplayable(config) {
  return (config?.method ?? 'get').toLowerCase() === 'get' && !config.ndaReplayed
}

export function holdForNdaAcceptance(client) {
  return async (error) => {
    if (!isNdaRequired(error)) throw error
    const accepted = requestNdaAcceptance()
    if (!isReplayable(error.config)) {
      error.ndaGateHandled = true
      throw error
    }
    await accepted
    return client.request({ ...error.config, ndaReplayed: true })
  }
}
