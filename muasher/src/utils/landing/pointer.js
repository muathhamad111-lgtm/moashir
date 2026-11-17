const FINE_POINTER = '(hover: hover) and (pointer: fine)'

export function hasFinePointer() {
  return typeof window.matchMedia === 'function' && window.matchMedia(FINE_POINTER).matches
}
