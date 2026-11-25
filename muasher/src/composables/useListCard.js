import { inject, provide, reactive } from 'vue'

const LIST_CARD = Symbol('listCard')

// What a list card shares between its parts: whether its toolbar is there to hold the table's
// «الأعمدة» menu, and that menu's state once the table offers one.
export function provideListCard() {
  const card = reactive({ hasToolbar: false, columnChoice: null })
  provide(LIST_CARD, card)
  return card
}

export function useListCard() {
  return inject(LIST_CARD, null)
}
