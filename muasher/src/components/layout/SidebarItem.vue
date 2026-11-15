<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  item: { type: Object, required: true },
  active: { type: Boolean, default: false },
})

const emit = defineEmits(['hint'])
const { t } = useI18n()
const label = computed(() => props.item.label ?? t(props.item.labelKey))
</script>

<template>
  <router-link
    class="sidebar__item"
    :class="[{ 'is-active': active }, item.path ? `sidebar__item--${item.path}` : '']"
    :to="{ name: item.routeName, query: item.query }"
    :aria-current="active ? 'page' : undefined"
    :aria-label="item.accessibleLabel ?? label"
    @mouseenter="emit('hint', label, $event.currentTarget)"
    @focus="emit('hint', label, $event.currentTarget)"
    @mouseleave="emit('hint', null)"
    @blur="emit('hint', null)"
  >
    <AppIcon :name="item.icon" class="sidebar__icon" />
    <span class="sidebar__label">{{ label }}</span>
    <span v-if="item.count" class="sidebar__count" aria-hidden="true">
      <bdi>{{ item.count }}</bdi>
    </span>
  </router-link>
</template>
