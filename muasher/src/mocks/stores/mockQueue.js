export const MOCK_QUEUE_DELAY_MS = 1500

const pending = new Set()

export function dispatchQueuedJob(job, delayMs = MOCK_QUEUE_DELAY_MS) {
  const entry = { job, timer: null }
  entry.timer = setTimeout(() => {
    pending.delete(entry)
    job()
  }, delayMs)
  pending.add(entry)
}

export function runQueuedJobs() {
  const entries = [...pending]
  pending.clear()
  entries.forEach((entry) => {
    clearTimeout(entry.timer)
    entry.job()
  })
}

export function discardQueuedJobs() {
  pending.forEach((entry) => clearTimeout(entry.timer))
  pending.clear()
}
