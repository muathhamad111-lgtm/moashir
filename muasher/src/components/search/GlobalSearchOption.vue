<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { SEARCH_TYPE_ICONS, SEARCH_TYPES } from '@/constants/globalSearch'
import { STATUS_VARIANTS } from '@/constants/statuses'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import FrozenBadge from '@/components/entities/FrozenBadge.vue'

const RECENT_ICON = 'fa-solid fa-clock-rotate-left'

const props = defineProps({
  id: { type: String, required: true },
  item: { type: Object, required: true },
  title: { type: String, required: true },
  active: { type: Boolean, default: false },
})

const { t, locale } = useI18n()

const icon = computed(() => SEARCH_TYPE_ICONS[props.item.type] ?? RECENT_ICON)
const isUser = computed(() => props.item.type === SEARCH_TYPES.users)
const email = computed(() => (isUser.value ? (props.item.email ?? '') : ''))

const subtitle = computed(() => {
  if (props.item.type === SEARCH_TYPES.requests) {
    const parts = [t('globalSearch.request.reference', { reference: props.item.reference })]
    if (props.item.path) parts.push(t(`requests.paths.${props.item.path}`))
    return parts.join(' · ')
  }
  if (isUser.value) {
    const labels = (props.item.roles ?? []).map((role) => t(`users.roleLabels.${role}`))
    return new Intl.ListFormat(locale.value, { style: 'short', type: 'unit' }).format(labels)
  }
  return ''
})

const accessibleLabel = computed(() => {
  if (!email.value) return undefined
  const values = { name: props.title, email: email.value, roles: subtitle.value }
  return subtitle.value
    ? t('globalSearch.user.label', values)
    : t('globalSearch.user.labelWithoutRoles', values)
})

const showStatus = computed(
  () => props.item.type === SEARCH_TYPES.requests && props.item.status in STATUS_VARIANTS,
)
</script>

<template>
  <li
    :id="id"
    role="option"
    class="global-search__option"
    :class="{ 'is-active': active }"
    :aria-selected="active ? 'true' : 'false'"
    :aria-label="accessibleLabel"
  >
    <i class="global-search__option-icon" :class="icon" aria-hidden="true"></i>
    <span class="global-search__option-text">
      <span class="global-search__option-title">{{ title }}</span>
      <span v-if="email" class="global-search__option-email" dir="ltr">{{ email }}</span>
      <span v-if="subtitle" class="global-search__option-subtitle">{{ subtitle }}</span>
    </span>
    <StatusBadge v-if="showStatus" :status="item.status" />
    <FrozenBadge v-if="item.isFrozen" />
  </li>
</template>
