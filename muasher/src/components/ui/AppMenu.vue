<script setup>
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCompactViewport } from '@/composables/useCompactViewport'
import { useMenuNavigation } from '@/composables/useMenuNavigation'
import { DROPDOWN_PLACEMENTS, MENU_TRIGGERS } from '@/constants/dropdown'
import DropdownSurface from '@/components/ui/DropdownSurface.vue'
import DropdownOption from '@/components/ui/DropdownOption.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const TRIGGER_CLASSES = Object.freeze({
  [MENU_TRIGGERS.icon]: 'btn btn--ghost btn--sm btn--icon',
  [MENU_TRIGGERS.avatar]: 'navbar__avatar',
})

const DANGER = 'danger'

const props = defineProps({
  actions: { type: Array, required: true },
  label: { type: String, default: '' },
  trigger: {
    type: String,
    default: MENU_TRIGGERS.icon,
    validator: (value) => Object.values(MENU_TRIGGERS).includes(value),
  },
})

const emit = defineEmits(['select'])

const { t } = useI18n()
const menuId = useId()
const rootRef = ref(null)
const triggerRef = ref(null)
const menuRef = ref(null)
const isOpen = ref(false)
const isCompact = useCompactViewport()

const menuLabel = computed(() => props.label || t('components.actions.label'))

const nav = useMenuNavigation({
  items: () => props.actions,
  menuRef,
  onEscape: close,
})

function open(focus = nav.focusFirst) {
  if (isOpen.value) return
  isOpen.value = true
  focus()
}

function dismiss() {
  isOpen.value = false
  nav.reset()
}

function close() {
  dismiss()
  triggerRef.value?.focus()
}

function toggle() {
  if (isOpen.value) close()
  else open()
}

function choose(action) {
  if (action.disabled) return
  close()
  emit('select', action.key)
}

function isDanger(action) {
  return action.variant === DANGER
}

function startsDangerZone(index) {
  return index > 0 && isDanger(props.actions[index]) && !isDanger(props.actions[index - 1])
}

function onTriggerKeydown(event) {
  const openers = { ArrowDown: nav.focusFirst, ArrowUp: nav.focusLast }
  if (!openers[event.key]) return
  event.preventDefault()
  open(openers[event.key])
}

function onMenuKeydown(event) {
  if (event.key === 'Tab' && !isCompact.value) {
    dismiss()
    return
  }
  nav.onKeydown(event)
}
</script>

<template>
  <div ref="rootRef" class="dropdown-menu">
    <button
      ref="triggerRef"
      type="button"
      :class="TRIGGER_CLASSES[trigger]"
      aria-haspopup="menu"
      :aria-expanded="isOpen ? 'true' : 'false'"
      :aria-controls="isOpen ? menuId : undefined"
      :aria-label="menuLabel"
      @click="toggle"
      @keydown="onTriggerKeydown"
    >
      <slot name="trigger">
        <AppIcon name="dots-vertical" />
      </slot>
    </button>
    <DropdownSurface
      :open="isOpen"
      :anchor="triggerRef"
      :boundary="rootRef"
      :placement="DROPDOWN_PLACEMENTS.menu"
      :sheet="isCompact"
      :title="menuLabel"
      @dismiss="isCompact ? close() : dismiss()"
    >
      <div v-if="$slots.header" class="dropdown-menu__header">
        <slot name="header" />
      </div>
      <ul
        :id="menuId"
        ref="menuRef"
        class="dropdown-menu__list"
        role="menu"
        :aria-label="menuLabel"
        @keydown="onMenuKeydown"
      >
        <template v-for="(action, index) in actions" :key="action.key">
          <li v-if="startsDangerZone(index)" class="dropdown-menu__separator" role="separator"></li>
          <li class="dropdown-menu__item" role="none">
            <DropdownOption
              tag="button"
              type="button"
              role="menuitem"
              tabindex="-1"
              :label="action.label"
              :icon="action.icon"
              :danger="isDanger(action)"
              :disabled="action.disabled"
              :active="index === nav.activeIndex.value"
              :checkable="false"
              :aria-disabled="action.disabled || undefined"
              @mousemove="nav.hover(index)"
              @click="choose(action)"
            />
          </li>
        </template>
      </ul>
    </DropdownSurface>
  </div>
</template>
