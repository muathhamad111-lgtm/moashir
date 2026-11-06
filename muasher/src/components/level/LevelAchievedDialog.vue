<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { TIER_ORDER } from '@/constants/levels'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { tierPalette } from '@/utils/levelTier'
import BaseModal from '@/components/modal/BaseModal.vue'
import LevelEmblem from './LevelEmblem.vue'

const props = defineProps({
  open: { type: Boolean, required: true },
  tier: { type: String, required: true, validator: (value) => TIER_ORDER.includes(value) },
})

const emit = defineEmits(['acknowledge'])
const { t } = useI18n()
const title = computed(() => t('level.achieved.title', { level: t(`level.names.${props.tier}`) }))

function onOpenChange(value) {
  if (!value) emit('acknowledge')
}
</script>

<template>
  <BaseModal :model-value="open" :title="title" size="sm" @update:model-value="onOpenChange">
    <div class="level-achieved__body" :class="`level-tone--${tierPalette(tier)}`">
      <LevelEmblem class="level-achieved__emblem" :tier="tier" size="l" />
      <p class="level-achieved__message">{{ t('level.achieved.message') }}</p>
    </div>
    <template #footer>
      <button type="button" class="btn btn--ghost" @click="emit('acknowledge')">
        {{ t('level.achieved.later') }}
      </button>
      <router-link
        class="btn btn--primary"
        data-autofocus
        :to="{ name: ROUTE_NAMES.CERTIFICATES_INDEX }"
        @click="emit('acknowledge')"
      >
        {{ t('level.achieved.view') }}
      </router-link>
    </template>
  </BaseModal>
</template>
