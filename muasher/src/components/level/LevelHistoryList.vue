<script setup>
import { useI18n } from 'vue-i18n'
import { scorePercentText } from '@/utils/percentText'
import { riyadhDateKey } from '@/utils/datetime'
import LevelEmblem from './LevelEmblem.vue'

defineProps({
  items: { type: Array, required: true },
})

const { t, n } = useI18n()
</script>

<template>
  <ol class="level-history" data-testid="level-history">
    <li v-for="item in items" :key="item.id" class="level-history__item">
      <LevelEmblem :tier="item.tier" size="list" />
      <div class="level-history__copy">
        <p class="level-history__name">
          {{ t(`level.names.${item.tier}`) }}
          <span
            class="level-history__chip"
            :class="item.isCurrent ? 'level-history__chip--current' : ''"
          >
            {{
              item.isCurrent ? t('level.history.current') : t(`level.history.status.${item.status}`)
            }}
          </span>
        </p>
        <p class="level-history__meta">
          <bdi dir="ltr">{{ item.number }}</bdi>
          <span aria-hidden="true">·</span>
          <bdi dir="ltr">{{ riyadhDateKey(item.issuedAt) }}</bdi>
        </p>
      </div>
      <bdi v-if="item.percent" class="level-history__percent" dir="ltr">
        {{ scorePercentText(n, t, item.percent) }}
      </bdi>
    </li>
  </ol>
</template>
