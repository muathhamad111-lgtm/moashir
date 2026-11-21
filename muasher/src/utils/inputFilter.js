export function applyInputFilter(input, filter) {
  const filtered = filter(input.value)
  if (filtered === input.value) return filtered
  const removed = input.value.length - filtered.length
  const caret = Math.max(0, (input.selectionStart ?? input.value.length) - removed)
  input.value = filtered
  input.setSelectionRange?.(caret, caret)
  return filtered
}
