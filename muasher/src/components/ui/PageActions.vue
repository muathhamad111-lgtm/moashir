<script setup>
import { Comment, Fragment, ref, useSlots } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCompactViewport } from '@/composables/useCompactViewport'
import BaseModal from '@/components/modal/BaseModal.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

// A page header's actions. From md up they sit in a row as before. On a phone a single action stays a
// button; two or more collapse into one «الإجراءات» button that opens a bottom sheet listing them
// (each at full width, a destructive one still red). Choosing an action closes the sheet first.
defineOptions({ inheritAttrs: false })

const props = defineProps({
  label: { type: String, default: '' },
})

const { t } = useI18n()
const slots = useSlots()
const isPhone = useCompactViewport()
const isOpen = ref(false)

function countRendered(nodes) {
  return nodes.reduce((sum, node) => {
    if (node.type === Comment) return sum
    if (node.type === Fragment) return sum + countRendered(node.children ?? [])
    return sum + 1
  }, 0)
}

const collapses = () => isPhone.value && countRendered(slots.default?.() ?? []) > 1
const title = () => props.label || t('pageActions.title')

// A link or button inside is an action; a menu trigger (aria-haspopup) opens its own list instead.
function closeOnChoice(event) {
  const control = event.target.closest('a, button')
  if (control && !control.hasAttribute('aria-haspopup')) isOpen.value = false
}
</script>

<template>
  <div v-if="!collapses()" v-bind="$attrs">
    <slot />
  </div>
  <div v-else class="page-actions" v-bind="$attrs">
    <button
      type="button"
      class="btn btn--secondary page-actions__toggle"
      aria-haspopup="dialog"
      :aria-expanded="isOpen ? 'true' : 'false'"
      data-testid="page-actions-toggle"
      @click="isOpen = true"
    >
      <AppIcon name="dots-horizontal" />
      <span>{{ t('pageActions.button') }}</span>
    </button>
    <BaseModal v-model="isOpen" :title="title()" layout="sheet">
      <div class="page-actions__sheet" data-testid="page-actions-sheet" @click="closeOnChoice">
        <slot />
      </div>
    </BaseModal>
  </div>
</template>
