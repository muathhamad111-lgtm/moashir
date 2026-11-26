export const DOT_GAP = 16
export const DOT_RADIUS = 1.3
export const LENS_RADIUS = 240
const FULL_TURN = 6.2832
const SPARK_THRESHOLD = 0.975
const SHIELD_SOFTNESS = 70
const SHIELD_STRENGTH = 0.85
const GOLD = '211,175,55'

const PALETTE = Object.freeze({
  dark: { base: '152,216,211', baseAlpha: 0.08, strongAlpha: 0.62 },
  light: { base: '29,122,118', baseAlpha: 0.07, strongAlpha: 0.55 },
})

export function cellHash(i, j) {
  const value = Math.sin(i * 127.1 + j * 311.7) * 43758.5453
  return value - Math.floor(value)
}

export function sparkCells(width, height) {
  const cells = []
  const cols = Math.ceil(width / DOT_GAP)
  const rows = Math.ceil(height / DOT_GAP)
  for (let i = 0; i < cols; i++) {
    for (let j = 0; j < rows; j++) {
      const r = cellHash(i, j)
      if (r > SPARK_THRESHOLD) cells.push([i, j, r])
    }
  }
  return cells
}

export function copyShield(box, visibility) {
  return (x, y) => {
    if (visibility < 0.02) return 1
    const ox = Math.max(box.x0 - x, 0, x - box.x1)
    const oy = Math.max(box.y0 - y, 0, y - box.y1)
    const outside = Math.min(1, Math.hypot(ox, oy) / SHIELD_SOFTNESS)
    return 1 - visibility * (1 - outside) * SHIELD_STRENGTH
  }
}

function dot(ctx, x, y, radius, fill) {
  ctx.fillStyle = fill
  ctx.beginPath()
  ctx.arc(x, y, radius, 0, FULL_TURN)
  ctx.fill()
}

function drawGrid(ctx, frame) {
  const { width, height, palette, lens, shield, box, visibility } = frame
  const cols = Math.ceil(width / DOT_GAP)
  const rows = Math.ceil(height / DOT_GAP)
  const off = DOT_GAP / 2
  const radius2 = LENS_RADIUS * LENS_RADIUS
  for (let j = 0; j < rows; j++) {
    const y = j * DOT_GAP + off
    const rowAlpha = palette.baseAlpha * (0.45 + 0.55 * (y / height))
    const inBand = visibility > 0.02 && y > box.y0 - SHIELD_SOFTNESS && y < box.y1 + SHIELD_SOFTNESS
    ctx.fillStyle = `rgba(${palette.base},${rowAlpha.toFixed(3)})`
    ctx.beginPath()
    for (let i = 0; i < cols; i++) {
      const x = i * DOT_GAP + off
      const nearLens = lens.active && (x - lens.x) ** 2 + (y - lens.y) ** 2 < radius2
      const nearCopy = inBand && x > box.x0 - SHIELD_SOFTNESS && x < box.x1 + SHIELD_SOFTNESS
      if (nearLens) continue
      if (nearCopy) {
        ctx.fill()
        dot(ctx, x, y, DOT_RADIUS, `rgba(${palette.base},${(rowAlpha * shield(x, y)).toFixed(3)})`)
        ctx.fillStyle = `rgba(${palette.base},${rowAlpha.toFixed(3)})`
        ctx.beginPath()
        continue
      }
      ctx.moveTo(x + DOT_RADIUS, y)
      ctx.arc(x, y, DOT_RADIUS, 0, FULL_TURN)
    }
    ctx.fill()
  }
}

function drawLens(ctx, frame) {
  const { width, height, palette, lens, shield, tick } = frame
  const cols = Math.ceil(width / DOT_GAP)
  const rows = Math.ceil(height / DOT_GAP)
  const off = DOT_GAP / 2
  const i0 = Math.max(0, Math.floor((lens.x - LENS_RADIUS) / DOT_GAP))
  const i1 = Math.min(cols - 1, Math.ceil((lens.x + LENS_RADIUS) / DOT_GAP))
  const j0 = Math.max(0, Math.floor((lens.y - LENS_RADIUS) / DOT_GAP))
  const j1 = Math.min(rows - 1, Math.ceil((lens.y + LENS_RADIUS) / DOT_GAP))
  for (let j = j0; j <= j1; j++) {
    const y0 = j * DOT_GAP + off
    const rowAlpha = palette.baseAlpha * (0.45 + 0.55 * (y0 / height))
    for (let i = i0; i <= i1; i++) {
      const x0 = i * DOT_GAP + off
      const dx = x0 - lens.x
      const dy = y0 - lens.y
      const distance = Math.sqrt(dx * dx + dy * dy) || 1
      if (distance >= LENS_RADIUS) continue
      const k = 1 - distance / LENS_RADIUS
      const energy = k * k * (3 - 2 * k) * lens.power
      const push =
        k * k * 11 * lens.power + Math.sin(tick * 0.05 - distance * 0.045) * 1.4 * k * lens.power
      const x = x0 + (dx / distance) * push
      const y = y0 + (dy / distance) * push
      const guard = shield(x, y)
      const fill =
        cellHash(i, j) > SPARK_THRESHOLD
          ? `rgba(${GOLD},${((0.18 + 0.45 * energy) * guard).toFixed(3)})`
          : `rgba(${palette.base},${((rowAlpha + (palette.strongAlpha - rowAlpha) * energy) * guard).toFixed(3)})`
      dot(ctx, x, y, DOT_RADIUS + energy * 0.8, fill)
    }
  }
}

function drawSparks(ctx, frame) {
  const { sparks, lens, shield, tick } = frame
  const off = DOT_GAP / 2
  const radius2 = LENS_RADIUS * LENS_RADIUS
  for (const [i, j, r] of sparks) {
    const x = i * DOT_GAP + off
    const y = j * DOT_GAP + off
    if (lens.active && (x - lens.x) ** 2 + (y - lens.y) ** 2 < radius2) continue
    const twinkle = Math.sin(tick * 0.03 + r * 60)
    if (twinkle < 0.55) continue
    dot(
      ctx,
      x,
      y,
      1.6,
      `rgba(${GOLD},${((0.1 + 0.18 * (twinkle - 0.55)) * shield(x, y)).toFixed(3)})`,
    )
  }
}

export function paletteFor(isDark) {
  return isDark ? PALETTE.dark : PALETTE.light
}

export function drawDotField(ctx, frame) {
  ctx.clearRect(0, 0, frame.width, frame.height)
  drawGrid(ctx, frame)
  if (frame.lens.active) drawLens(ctx, frame)
  drawSparks(ctx, frame)
}
