const REFERENCE = /([A-Z]{2,4}-\d{4}-\d{6})/

export function referenceSegments(text) {
  return String(text ?? '')
    .split(REFERENCE)
    .filter(Boolean)
    .map((part, index) => ({
      key: `${index}-${part}`,
      text: part,
      isReference: REFERENCE.test(part),
    }))
}
