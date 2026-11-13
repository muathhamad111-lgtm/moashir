import { readonly, ref } from 'vue'

const paused = ref(false)

export function useAmbientMotion() {
  function toggle() {
    paused.value = !paused.value
  }

  return { paused: readonly(paused), toggle }
}
