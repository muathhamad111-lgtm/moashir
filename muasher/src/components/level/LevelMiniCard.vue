<script setup>
import { useI18n } from 'vue-i18n'
import { TIER_ORDER } from '@/constants/levels'
import LevelEmblem from './LevelEmblem.vue'

defineProps({
  tier: { type: String, required: true, validator: (value) => TIER_ORDER.includes(value) },
  action: { type: Object, required: true },
  collapsed: { type: Boolean, default: false },
})

const { t } = useI18n()
</script>

<template>
  <router-link
    class="level-mini-card"
    :class="{ 'level-mini-card--collapsed': collapsed }"
    :to="{ name: action.routeName }"
    :aria-label="`${t(`level.names.${tier}`)} · ${t(action.actionKey)}`"
    data-testid="level-mini-card"
  >
    <LevelEmblem :tier="tier" size="list" />
    <span v-if="!collapsed" class="level-mini-card__text">
      <span class="level-mini-card__eyebrow">{{ t(`level.names.${tier}`) }}</span>
      <span class="level-mini-card__action">{{ t(action.actionKey) }}</span>
    </span>
  </router-link>
</template>
