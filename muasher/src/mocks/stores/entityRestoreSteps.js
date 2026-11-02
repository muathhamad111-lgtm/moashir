const steps = new Set()

export function registerEntityRestoreStep(step) {
  steps.add(step)
}

export function runEntityRestoreSteps(entity) {
  steps.forEach((step) => step(entity))
}
