<script setup>
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { MODAL_ROLES, MODAL_TONES } from '@/constants/modal'
import { useFocusTrap } from '@/composables/useFocusTrap'
import { useSheetDrag } from '@/composables/useSheetDrag'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg', 'xl'].includes(value),
  },
  closeOnBackdrop: { type: Boolean, default: true },
  // A mandatory step (the staff agreement): no close button, and Esc and the backdrop do nothing.
  dismissible: { type: Boolean, default: true },
  role: {
    type: String,
    default: MODAL_ROLES.DIALOG,
    validator: (value) => Object.values(MODAL_ROLES).includes(value),
  },
  icon: { type: String, default: '' },
  tone: {
    type: String,
    default: MODAL_TONES.CONFIRM,
    validator: (value) => Object.values(MODAL_TONES).includes(value),
  },
  describedBy: { type: String, default: '' },
  // dialog: centred. drawer: a side panel at the inline end. drawer-start: a side panel at the
  // inline start (the phone navigation). sheet: a bottom sheet with a drag handle (phones).
  layout: {
    type: String,
    default: 'dialog',
    validator: (value) => ['dialog', 'drawer', 'drawer-start', 'sheet'].includes(value),
  },
})

const emit = defineEmits(['update:modelValue', 'close', 'interact'])

const { t } = useI18n()
const panelRef = ref(null)
const titleId = useId()
const subtitleId = useId()

const isCentered = computed(() => Boolean(props.icon))
const description = computed(() => props.describedBy || (props.subtitle ? subtitleId : undefined))

function close() {
  if (!props.dismissible) return
  emit('update:modelValue', false)
  emit('close')
}

const isSideDrawer = computed(() => props.layout === 'drawer' || props.layout === 'drawer-start')
const sheetDrag = useSheetDrag(panelRef, { onDismiss: close })

const { onKeydown: trapKeydown } = useFocusTrap(panelRef, {
  isActive: () => props.modelValue,
  onEscape: close,
})

function onKeydown(event) {
  emit('interact')
  trapKeydown(event)
}

function onBackdropClick() {
  if (props.closeOnBackdrop) close()
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="modal is-open"
      :class="[
        `modal--${size}`,
        {
          'modal--centered': isCentered,
          'modal--drawer': isSideDrawer,
          'modal--drawer-start': layout === 'drawer-start',
          'modal--sheet': layout === 'sheet',
        },
      ]"
      @keydown="onKeydown"
    >
      <div class="modal__backdrop" @click="onBackdropClick"></div>
      <div
        ref="panelRef"
        class="modal__panel"
        :role="role"
        aria-modal="true"
        :aria-labelledby="title ? titleId : undefined"
        :aria-describedby="description"
        tabindex="-1"
        @pointerenter="emit('interact')"
      >
        <div
          v-if="layout === 'sheet' && dismissible"
          class="modal__handle"
          aria-hidden="true"
          @pointerdown="sheetDrag.start"
        ></div>
        <header class="modal__header">
          <span v-if="icon" class="modal__icon" :class="`modal__icon--${tone}`" aria-hidden="true">
            <AppIcon :name="icon" size="lg" />
          </span>
          <slot name="header">
            <div class="modal__heading">
              <h2 :id="titleId" class="modal__title">{{ title }}</h2>
              <p v-if="subtitle" :id="subtitleId" class="modal__subtitle">
                <bdi>{{ subtitle }}</bdi>
              </p>
            </div>
          </slot>
          <button
            v-if="dismissible"
            type="button"
            class="modal__close"
            :aria-label="t('actions.close')"
            @click="close"
          >
            <AppIcon name="x" />
          </button>
        </header>
        <div v-if="$slots.default" class="modal__body">
          <slot />
        </div>
        <footer v-if="$slots.footer" class="modal__footer">
          <slot name="footer" />
        </footer>
      </div>
    </div>
  </Teleport>
</template>
