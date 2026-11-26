export function tweenNumber({ to, delay, duration, easing, onUpdate }) {
  let frameId = null
  let begin = null

  function step(now) {
    begin ??= now + delay
    const ratio = Math.min(1, Math.max(0, (now - begin) / duration))
    onUpdate(ratio < 1 ? to * easing(ratio) : to)
    if (ratio < 1) frameId = requestAnimationFrame(step)
  }

  onUpdate(0)
  frameId = requestAnimationFrame(step)
  return () => {
    if (frameId !== null) cancelAnimationFrame(frameId)
  }
}
