import { ref } from 'vue'

export function useIdempotencyKey() {
  const key = ref(null)

  function current() {
    if (!key.value) key.value = crypto.randomUUID()
    return key.value
  }

  function renew() {
    key.value = null
  }

  return { key, current, renew }
}
