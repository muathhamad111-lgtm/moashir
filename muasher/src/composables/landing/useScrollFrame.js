import { onBeforeUnmount, onMounted } from 'vue'

export function useScrollFrame(render, { onResize = null } = {}) {
  let pending = false

  function schedule() {
    if (pending) return
    pending = true
    requestAnimationFrame(() => {
      pending = false
      render()
    })
  }

  function resize() {
    onResize?.()
    schedule()
  }

  onMounted(() => {
    window.addEventListener('scroll', schedule, { passive: true })
    window.addEventListener('resize', resize)
    render()
  })

  onBeforeUnmount(() => {
    window.removeEventListener('scroll', schedule)
    window.removeEventListener('resize', resize)
  })

  return { schedule }
}
