import { computed, ref, watch } from 'vue'
import { rowKeyOf } from '@/utils/rowKey'
import { moveIndex } from '@/utils/reorder'
import { newMenuLink } from '@/utils/siteMenu'

function locate(rows, key) {
  for (const [index, row] of rows.entries()) {
    if (rowKeyOf(row) === key) return { row, index, parent: null, parentIndex: null }
    const childIndex = (row.children ?? []).findIndex((child) => rowKeyOf(child) === key)
    if (childIndex >= 0)
      return { row: row.children[childIndex], index: childIndex, parent: row, parentIndex: index }
  }
  return null
}

export function useMenuEditor(rows) {
  const selectedKey = ref(null)
  const selection = computed(() =>
    selectedKey.value ? locate(rows.value, selectedKey.value) : null,
  )

  function select(row) {
    selectedKey.value = row ? rowKeyOf(row) : null
  }

  function addLink() {
    rows.value.push(newMenuLink())
    select(rows.value.at(-1))
  }

  function addHeaderLink() {
    rows.value.push({ ...newMenuLink(), children: [] })
    select(rows.value.at(-1))
  }

  function addChild(parent) {
    parent.children.push(newMenuLink())
    select(parent.children.at(-1))
  }

  function remove() {
    const found = selection.value
    if (!found) return
    const siblings = found.parent ? found.parent.children : rows.value
    siblings.splice(found.index, 1)
    select(siblings[Math.min(found.index, siblings.length - 1)] ?? found.parent ?? rows.value[0])
  }

  function move(list, from, to) {
    list.splice(0, list.length, ...moveIndex(list, from, to))
  }

  watch(
    rows,
    (current) => {
      if (!selection.value) select(current[0])
    },
    { immediate: true },
  )

  return { selection, selectedKey, select, addLink, addHeaderLink, addChild, remove, move }
}
