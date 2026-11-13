import { computed, nextTick, onScopeDispose, ref, shallowRef, watch } from 'vue'
import { REMOTE_SEARCH_DELAY_MS } from '@/constants/dropdown'
import { useActiveIndex } from '@/composables/useActiveIndex'
import { sameValue, valueKey } from '@/utils/dropdown/optionValue'
import { matchesQuery } from '@/utils/text/normalizeArabic'

export function useComboboxListbox({ props, emit, isCompact, focusInput, listRef }) {
  const isOpen = ref(false)
  const query = ref('')
  const isEditing = ref(false)
  const known = shallowRef({})
  let searchTimer = null

  const selectedValues = computed(() => {
    if (props.multiple) return Array.isArray(props.modelValue) ? props.modelValue : []
    return known.value[valueKey(props.modelValue)] ? [props.modelValue] : []
  })
  const selectedOptions = computed(() =>
    selectedValues.value.map((value) => known.value[valueKey(value)]).filter(Boolean),
  )
  const visibleOptions = computed(() =>
    props.remote
      ? props.options
      : props.options.filter((option) => matchesQuery(option.label, query.value)),
  )
  const inputText = computed(() => {
    if (props.multiple || isEditing.value) return query.value
    return selectedOptions.value[0]?.label ?? ''
  })

  const active = useActiveIndex(() => visibleOptions.value.length)

  function remember(options) {
    known.value = {
      ...known.value,
      ...Object.fromEntries(options.map((option) => [valueKey(option.value), option])),
    }
  }

  function open() {
    if (props.disabled || isOpen.value) return
    isOpen.value = true
    const selectedIndex = visibleOptions.value.findIndex((option) =>
      sameValue(option.value, selectedValues.value[0]),
    )
    active.set(Math.max(selectedIndex, 0))
    emit('open')
  }

  function dismiss() {
    if (!isOpen.value) return
    isOpen.value = false
    isEditing.value = false
    query.value = ''
    active.clear()
    emit('close')
  }

  function close() {
    const returnsFocus = isOpen.value && isCompact.value
    dismiss()
    if (returnsFocus) nextTick(focusInput)
  }

  function isSelected(option) {
    return selectedValues.value.some((value) => sameValue(value, option.value))
  }

  function toggleValue(option) {
    const next = isSelected(option)
      ? selectedValues.value.filter((value) => !sameValue(value, option.value))
      : [...selectedValues.value, option.value]
    emit('update:modelValue', next)
    query.value = ''
  }

  function choose(index) {
    const option = visibleOptions.value[index]
    if (!option || option.disabled) return
    remember([option])
    if (props.multiple) {
      toggleValue(option)
      return
    }
    if (!sameValue(option.value, props.modelValue)) emit('update:modelValue', option.value)
    close()
  }

  function remove(option) {
    emit(
      'update:modelValue',
      selectedValues.value.filter((value) => !sameValue(value, option.value)),
    )
  }

  function onInput(event) {
    query.value = event.target.value
    isEditing.value = true
    open()
  }

  function toggle() {
    if (isOpen.value) close()
    else open()
  }

  function stepOrOpen(delta) {
    if (isOpen.value) active.step(delta)
    else open()
  }

  const KEYS = {
    ArrowDown: () => stepOrOpen(1),
    ArrowUp: () => stepOrOpen(-1),
    Enter: () => isOpen.value && choose(active.activeIndex.value),
  }

  function onEscape(event) {
    if (!isOpen.value && !query.value) return
    event.preventDefault()
    event.stopPropagation()
    if (isOpen.value) close()
    else query.value = ''
  }

  function onBackspace() {
    const last = selectedOptions.value.at(-1)
    if (props.multiple && !query.value && last) remove(last)
  }

  function onTab() {
    if (isCompact.value) return
    if (!props.multiple && isOpen.value) choose(active.activeIndex.value)
    dismiss()
  }

  function onKeydown(event) {
    if (event.key === 'Escape') return onEscape(event)
    if (event.key === 'Tab') return onTab()
    if (event.key === 'Backspace') return onBackspace()
    const handler = KEYS[event.key]
    if (!handler) return
    event.preventDefault()
    handler()
  }

  watch(() => props.options, remember, { immediate: true })

  watch(query, (value) => {
    if (isOpen.value) active.set(0)
    if (!props.remote) return
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => emit('search', value), REMOTE_SEARCH_DELAY_MS)
  })

  watch(
    () => visibleOptions.value.length,
    (count) => {
      if (isOpen.value && active.activeIndex.value >= count) active.set(0)
    },
  )

  watch(active.activeIndex, async (index) => {
    if (index < 0) return
    await nextTick()
    listRef()?.querySelector('.is-active')?.scrollIntoView?.({ block: 'nearest' })
  })

  onScopeDispose(() => clearTimeout(searchTimer))

  return {
    isOpen,
    query,
    inputText,
    activeIndex: active.activeIndex,
    selectedValues,
    selectedOptions,
    visibleOptions,
    open,
    close,
    dismiss,
    toggle,
    choose,
    remove,
    hover: active.set,
    onInput,
    onKeydown,
  }
}
