const SIZE = 29
const FINDER = 7
const ZONE = 8
const SEED = 8471
const DENSITY = 0.52

function generator(seed) {
  let state = seed
  return () => {
    state = (state * 9301 + 49297) % 233280
    return state / 233280
  }
}

function isFinderCell(x, y) {
  const inBox = x >= 0 && x < FINDER && y >= 0 && y < FINDER
  const ring = x === 0 || x === FINDER - 1 || y === 0 || y === FINDER - 1
  const core = x >= 2 && x <= 4 && y >= 2 && y <= 4
  return inBox && (ring || core)
}

function inFinderZone(x, y) {
  const far = SIZE - ZONE - 1
  return (x < ZONE && y < ZONE) || (x > far && y < ZONE) || (x < ZONE && y > far)
}

function localCoordinate(value) {
  return value > SIZE - ZONE - 1 ? value - (SIZE - FINDER) : value
}

export function qrPatternPath() {
  const random = generator(SEED)
  let d = ''
  for (let y = 0; y < SIZE; y++) {
    for (let x = 0; x < SIZE; x++) {
      const on = inFinderZone(x, y)
        ? isFinderCell(localCoordinate(x), localCoordinate(y))
        : random() > DENSITY
      if (on) d += `M${x} ${y}h1v1h-1z`
    }
  }
  return d
}

export const QR_SIZE = SIZE
