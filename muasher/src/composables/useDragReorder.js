import { ref } from 'vue'

export function useDragReorder(onMove) {
  const dragIndex = ref(null)
  const overIndex = ref(null)

  function start(index, event) {
    dragIndex.value = index
    if (event?.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move'
      event.dataTransfer.setData('text/plain', String(index))
    }
  }

  function over(index) {
    if (dragIndex.value !== null) overIndex.value = index
  }

  function end() {
    dragIndex.value = null
    overIndex.value = null
  }

  function drop(index) {
    const from = dragIndex.value
    end()
    if (from !== null && from !== index) onMove(from, index)
  }

  return { dragIndex, overIndex, start, over, drop, end }
}
