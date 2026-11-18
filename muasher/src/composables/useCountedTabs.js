import { computed, watch } from 'vue'

/**
 * Status tabs labelled with their counts, bound to a list filter. When the counts first arrive and
 * the selected tab is empty, it opens the first tab that has rows (once).
 *
 * @param {object} options
 * @param {string[]} options.values the tab values, in order
 * @param {(value: string) => string} options.labelOf the tab label without the count
 * @param {(value: string) => number|undefined} options.countOf the count from the list meta
 * @param {import('vue').Ref<object|undefined>} options.counts the meta counts, watched for arrival
 * @param {() => string} options.current the selected value
 * @param {(value: string) => void} options.select selects a value
 */
export function useCountedTabs({ values, labelOf, countOf, counts, current, select }) {
  const tabs = computed(() =>
    values.map((value) => {
      const count = countOf(value)
      return { value, label: count === undefined ? labelOf(value) : `${labelOf(value)} (${count})` }
    }),
  )
  const tab = computed({ get: current, set: select })

  const stopOpening = watch(counts, (arrived) => {
    if (!arrived) return
    stopOpening()
    if (countOf(current()) > 0) return
    const first = values.find((value) => countOf(value) > 0)
    if (first) select(first)
  })

  return { tabs, tab }
}
