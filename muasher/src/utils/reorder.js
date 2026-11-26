export function moveIndex(list, from, to) {
  if (from === to || from < 0 || to < 0 || from >= list.length || to >= list.length)
    return [...list]
  const next = [...list]
  const [moved] = next.splice(from, 1)
  next.splice(to, 0, moved)
  return next
}

export function sameOrder(left, right) {
  return left.length === right.length && left.every((value, index) => value === right[index])
}
