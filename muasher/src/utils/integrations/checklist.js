export function checklistSteps(items, labelOf) {
  return (items ?? []).map((item) => ({
    key: item.key,
    label: labelOf(item.key),
    ...(item.confirmable ? { done: item.confirmed === true } : {}),
  }))
}
