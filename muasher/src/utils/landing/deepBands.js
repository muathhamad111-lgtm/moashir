const DEEP_SELECTOR = '[data-lp-deep]'

export function isOverDeepBand(lineY) {
  return [...document.querySelectorAll(DEEP_SELECTOR)].some((band) => {
    const rect = band.getBoundingClientRect()
    return rect.top <= lineY && rect.bottom > lineY
  })
}
