<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  facts: { type: Array, required: true },
})

const { t } = useI18n()
</script>

<template>
  <section class="cycle-overview" :aria-label="t('cycles.detail.overview')">
    <dl class="cycle-overview__facts">
      <div v-for="fact in facts" :key="fact.key" class="cycle-overview__fact" :data-fact="fact.key">
        <span class="cycle-overview__icon"><AppIcon :name="fact.icon" /></span>
        <dt class="cycle-overview__label">{{ fact.label }}</dt>
        <dd class="cycle-overview__value">
          <bdi>{{ fact.value }}</bdi>
        </dd>
      </div>
    </dl>
  </section>
</template>
