import { inject, provide, ref, watch } from 'vue'

const READY_KEY = Symbol('landing-ready')

export function provideLandingReady() {
  const ready = ref(false)
  provide(READY_KEY, ready)
  return {
    ready,
    markReady: () => {
      ready.value = true
    },
  }
}

export function useLandingReady() {
  const ready = inject(READY_KEY, ref(true))

  function onReady(callback) {
    if (ready.value) {
      callback()
      return
    }
    const stop = watch(ready, (value) => {
      if (!value) return
      stop()
      callback()
    })
  }

  return { ready, onReady }
}
