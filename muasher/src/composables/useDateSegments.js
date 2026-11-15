import { computed, ref, watch } from 'vue'
import {
  DATE_SEGMENTS,
  isIncomplete,
  isoOf,
  segmentsFromText,
  segmentsOf,
  spillDigits,
} from '@/utils/dateSegments'

const BACKSPACE = 'Backspace'

export function useDateSegments(modelValue, emitValue) {
  const segments = ref(segmentsOf(modelValue.value))
  const inputs = {}
  const touched = ref(false)
  let lastEmitted = modelValue.value ?? ''

  watch(modelValue, (value) => {
    if ((value ?? '') === lastEmitted) return
    lastEmitted = value ?? ''
    segments.value = segmentsOf(lastEmitted)
  })

  function publish() {
    const iso = isoOf(segments.value)
    if (iso === lastEmitted) return
    lastEmitted = iso
    emitValue(iso)
  }

  function focusSegment(index) {
    const segment = DATE_SEGMENTS[index]
    if (segment) inputs[segment.key]?.focus()
  }

  function onSegmentInput(index, event) {
    const { segments: next, lastIndex } = spillDigits(segments.value, index, event.target.value)
    event.target.value = next[DATE_SEGMENTS[index].key]
    segments.value = next
    publish()
    const { key, length } = DATE_SEGMENTS[lastIndex]
    const target = next[key].length === length ? lastIndex + 1 : lastIndex
    focusSegment(Math.min(target, DATE_SEGMENTS.length - 1))
  }

  function onSegmentKeydown(index, event) {
    const { key } = DATE_SEGMENTS[index]
    if (event.key === BACKSPACE && segments.value[key] === '' && index > 0) {
      event.preventDefault()
      focusSegment(index - 1)
    }
  }

  function onPaste(event) {
    const pasted = segmentsFromText(event.clipboardData?.getData('text') ?? '')
    if (!pasted) return
    event.preventDefault()
    segments.value = pasted
    publish()
  }

  function setFromIso(iso) {
    segments.value = segmentsOf(iso)
    publish()
  }

  function bindInput(key) {
    return (element) => {
      inputs[key] = element
    }
  }

  const incomplete = computed(() => touched.value && isIncomplete(segments.value))

  return {
    segments,
    incomplete,
    touched,
    bindInput,
    onSegmentInput,
    onSegmentKeydown,
    onPaste,
    setFromIso,
  }
}
