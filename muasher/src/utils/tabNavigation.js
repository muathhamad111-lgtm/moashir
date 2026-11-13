const KEY_STEPS = Object.freeze({ ArrowRight: 1, ArrowLeft: -1, Home: 'first', End: 'last' })

export function isRightToLeft() {
  return document.documentElement.dir === 'rtl'
}

export function tabIndexAfterKey(key, index, count, rightToLeft) {
  const step = KEY_STEPS[key]
  if (step === undefined || !count) return null
  if (step === 'first') return 0
  if (step === 'last') return count - 1
  const direction = rightToLeft ? -step : step
  return (index + direction + count) % count
}
