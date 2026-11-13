<script setup>
import { computed, onMounted, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAttentionStrip } from '@/composables/useAttentionStrip'
import { formatNumber } from '@/utils/formatNumber'
import AttentionItem from './AttentionItem.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t, n } = useI18n()
const { views, visible, hasMore, first, others, expanded, toggle, load } = useAttentionStrip()
const listId = useId()

const summary = computed(() =>
  others.value.map((view) => t(`portal.strip.short.${view.id}`)).join(t('portal.strip.separator')),
)

onMounted(load)
</script>

<template>
  <section
    v-if="views.length"
    class="attention-strip"
    :aria-labelledby="`${listId}-title`"
    data-testid="attention-strip"
  >
    <div class="attention-strip__inner">
      <p :id="`${listId}-title`" class="attention-strip__title">
        <AppIcon name="warning-triangle" class="attention-strip__title-icon" />
        <span>{{ t('portal.strip.title') }}</span>
        <span class="attention-strip__count">{{ formatNumber(n, views.length) }}</span>
      </p>

      <ul :id="listId" class="attention-strip__list attention-strip__list--desktop">
        <li v-for="view in visible" :key="view.id" class="attention-strip__entry">
          <AttentionItem :view="view" />
        </li>
      </ul>

      <div class="attention-strip__mobile">
        <AttentionItem v-if="first && !expanded" :view="first" layout="row" />
        <ul v-else class="attention-strip__list attention-strip__list--stacked">
          <li v-for="view in views" :key="view.id" class="attention-strip__entry">
            <AttentionItem :view="view" layout="row" />
          </li>
        </ul>
        <p v-if="others.length && !expanded" class="attention-strip__summary">
          {{ t('portal.strip.more', { items: summary }) }}
        </p>
      </div>

      <button
        v-if="hasMore || others.length"
        type="button"
        class="attention-strip__toggle"
        :class="{ 'attention-strip__toggle--mobile-only': !hasMore }"
        :aria-expanded="expanded"
        :aria-controls="listId"
        @click="toggle"
      >
        {{ expanded ? t('portal.strip.showLess') : t('portal.strip.showAll') }}
      </button>
    </div>
  </section>
</template>
