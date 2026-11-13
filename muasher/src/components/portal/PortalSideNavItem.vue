<script setup>
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'
import AppIcon from '@/components/ui/AppIcon.vue'
import ComingSoonChip from './ComingSoonChip.vue'

defineProps({
  to: { type: Object, required: true },
  icon: { type: String, required: true },
  label: { type: String, required: true },
  active: { type: Boolean, default: false },
  count: { type: Number, default: 0 },
  countLabel: { type: String, default: '' },
  soon: { type: Boolean, default: false },
  collapsed: { type: Boolean, default: false },
})

const { t, n } = useI18n()
</script>

<template>
  <router-link
    class="side-nav-item"
    :class="{ 'is-active': active, 'side-nav-item--collapsed': collapsed }"
    :to="to"
    :aria-current="active ? 'page' : undefined"
    :title="collapsed ? (soon ? `${label} · ${t('portal.tools.soon')}` : label) : undefined"
  >
    <span class="side-nav-item__icon">
      <AppIcon :name="icon" />
      <span
        v-if="collapsed && (soon || count > 0)"
        class="side-nav-item__dot"
        aria-hidden="true"
      ></span>
    </span>
    <span :class="collapsed ? 'side-nav-item__sr' : 'side-nav-item__label'">{{ label }}</span>
    <template v-if="!collapsed">
      <ComingSoonChip v-if="soon" class="side-nav-item__soon" compact />
      <span v-else-if="count > 0" class="side-nav-item__count" aria-hidden="true">
        {{ formatNumber(n, count) }}
      </span>
    </template>
    <span v-if="soon && collapsed" class="side-nav-item__sr">{{ t('portal.tools.soon') }}</span>
    <span v-if="count > 0" class="side-nav-item__sr">{{ countLabel }}</span>
  </router-link>
</template>
