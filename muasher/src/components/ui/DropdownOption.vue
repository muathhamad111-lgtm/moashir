<script setup>
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  label: { type: String, required: true },
  description: { type: String, default: '' },
  icon: { type: String, default: '' },
  badge: { type: String, default: '' },
  tag: { type: String, default: 'li' },
  active: { type: Boolean, default: false },
  selected: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  danger: { type: Boolean, default: false },
  checkable: { type: Boolean, default: true },
})
</script>

<template>
  <component
    :is="tag"
    class="dropdown-option"
    :class="{
      'is-active': active,
      'is-selected': selected,
      'is-disabled': disabled,
      'is-danger': danger,
    }"
  >
    <AppIcon v-if="icon" :name="icon" class="dropdown-option__icon" />
    <span class="dropdown-option__text">
      <span class="dropdown-option__label">{{ label }}</span>
      <bdi v-if="description" class="dropdown-option__description">{{ description }}</bdi>
    </span>
    <span v-if="badge" class="dropdown-option__badge">{{ badge }}</span>
    <span v-if="checkable" class="dropdown-option__check">
      <AppIcon v-if="selected" name="check" />
    </span>
  </component>
</template>
