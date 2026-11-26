export const MODAL_ROLES = Object.freeze({
  DIALOG: 'dialog',
  ALERT_DIALOG: 'alertdialog',
})

export const MODAL_TONES = Object.freeze({
  CONFIRM: 'confirm',
  WARNING: 'warning',
  DANGER: 'danger',
  SUCCESS: 'success',
})

export const DECISION_VARIANTS = Object.freeze({
  CONFIRM: MODAL_TONES.CONFIRM,
  WARNING: MODAL_TONES.WARNING,
  DANGER: MODAL_TONES.DANGER,
})

export const DECISION_ICONS = Object.freeze({
  [MODAL_TONES.CONFIRM]: 'question-circle',
  [MODAL_TONES.WARNING]: 'warning-triangle',
  [MODAL_TONES.DANGER]: 'warning-triangle',
  [MODAL_TONES.SUCCESS]: 'check-circle',
})

export const SUCCESS_AUTO_CLOSE_MS = 4000
