// Pages mark the field the visitor types into next (the first code box, or the password
// when the identifier is already known); otherwise the heading takes focus.
export const AUTH_AUTOFOCUS = 'data-auth-autofocus'

const FOCUSABLE = 'input:not(:disabled), select:not(:disabled), textarea:not(:disabled)'

function markedField(page) {
  const marked = page.querySelector(`[${AUTH_AUTOFOCUS}]`)
  if (!marked) return null
  return marked.matches(FOCUSABLE) ? marked : marked.querySelector(FOCUSABLE)
}

function heading(page) {
  const title = page.querySelector('h1')
  if (title && !title.hasAttribute('tabindex')) title.setAttribute('tabindex', '-1')
  return title
}

export function focusArrival(page) {
  const target = markedField(page) ?? heading(page)
  target?.focus({ preventScroll: true })
}

export function shouldMoveFocus(page, hadFocus) {
  const active = document.activeElement
  if (active && page.contains(active)) return false
  return hadFocus || !active || active === document.body
}
