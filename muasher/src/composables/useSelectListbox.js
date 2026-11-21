import { computed, nextTick, ref, watch } from 'vue'
import { LISTBOX_PAGE_STEP } from '@/constants/dropdown'
import { useActiveIndex } from '@/composables/useActiveIndex'
import { isTypeaheadKey, useTypeahead } from '@/composables/useTypeahead'
import { optionIndexOf, sameValue } from '@/utils/dropdown/optionValue'

export function useSelectListbox({ props, emit, triggerRef, listRef, isCompact, commitsOnTab }) {
  const isOpen = ref(false)
  const options = () => props.options
  const active = useActiveIndex(() => options().length)
  const typeahead = useTypeahead(() => options().map((option) => option.label))

  const selectedIndex = computed(() => optionIndexOf(options(), props.modelValue))
  const selectedOption = computed(() => options()[selectedIndex.value] ?? null)

  function open(start = 'selected') {
    if (props.disabled || isOpen.value) return
    isOpen.value = true
    const starts = { first: active.first, last: active.last }
    if (starts[start]) starts[start]()
    else active.set(Math.max(selectedIndex.value, 0))
    emit('open')
    if (isCompact.value) nextTick(() => listRef()?.focus())
  }

  function dismiss() {
    if (!isOpen.value) return
    isOpen.value = false
    active.clear()
    typeahead.reset()
    emit('close')
  }

  function close() {
    const returnsFocus = isOpen.value && isCompact.value
    dismiss()
    if (returnsFocus) nextTick(() => triggerRef.value?.focus())
  }

  function choose(index) {
    const option = options()[index]
    if (!option || option.disabled) return
    if (!sameValue(option.value, props.modelValue)) emit('update:modelValue', option.value)
    close()
  }

  function toggle() {
    if (isOpen.value) close()
    else open()
  }

  function jumpTo(char) {
    const index = typeahead.type(char, active.activeIndex.value)
    if (!isOpen.value) open()
    if (index >= 0) active.set(index)
  }

  const CLOSED_KEYS = {
    ArrowDown: () => open(),
    ArrowUp: () => open(),
    Enter: () => open(),
    ' ': () => open(),
    Home: () => open('first'),
    End: () => open('last'),
  }

  const OPEN_KEYS = {
    ArrowDown: () => active.step(1),
    ArrowUp: (event) => (event.altKey ? choose(active.activeIndex.value) : active.step(-1)),
    Home: () => active.first(),
    End: () => active.last(),
    PageDown: () => active.step(LISTBOX_PAGE_STEP),
    PageUp: () => active.step(-LISTBOX_PAGE_STEP),
    Enter: () => choose(active.activeIndex.value),
    ' ': (event) => (typeahead.hasBuffer() ? jumpTo(event.key) : choose(active.activeIndex.value)),
  }

  function onTab() {
    if (isCompact.value) return
    if (commitsOnTab.value) choose(active.activeIndex.value)
    dismiss()
  }

  function onKeydown(event) {
    if (props.disabled) return
    if (event.key === 'Tab') return onTab()
    if (event.key === 'Escape') {
      if (!isOpen.value) return
      event.preventDefault()
      event.stopPropagation()
      close()
      return
    }
    const handler = (isOpen.value ? OPEN_KEYS : CLOSED_KEYS)[event.key]
    if (handler) {
      event.preventDefault()
      handler(event)
      return
    }
    if (isTypeaheadKey(event)) {
      event.preventDefault()
      jumpTo(event.key)
    }
  }

  watch(active.activeIndex, async (index) => {
    if (index < 0) return
    await nextTick()
    listRef()?.querySelector('.is-active')?.scrollIntoView?.({ block: 'nearest' })
  })

  return {
    isOpen,
    activeIndex: active.activeIndex,
    selectedOption,
    open,
    close,
    dismiss,
    toggle,
    choose,
    hover: active.set,
    onKeydown,
  }
}
