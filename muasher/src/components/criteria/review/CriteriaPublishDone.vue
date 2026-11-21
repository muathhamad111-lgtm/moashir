<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'

// After publishing: what went out and what it did to open requests, instead of a long toast.
const props = defineProps({
  outcome: { type: Object, required: true },
  changeCount: { type: Number, default: 0 },
})

const { t } = useI18n()

const version = computed(() => props.outcome.data ?? {})
const scope = computed(() => props.outcome.meta?.effect?.scope ?? null)
const affected = computed(() => props.outcome.meta?.affectedOpenCount ?? 0)
</script>

<template>
  <section class="criteria-publish-done" role="status">
    <span class="criteria-publish-done__icon" aria-hidden="true"
      ><AppIcon name="check-circle"
    /></span>
    <h2 class="criteria-publish-done__title">
      {{ t('criteria.publish.success', { number: version.versionNumber }) }}
    </h2>
    <ul class="criteria-publish-done__facts">
      <li v-if="changeCount">
        {{ t('criteria.review.done.changes', { count: changeCount }, changeCount) }}
      </li>
      <li v-if="scope">{{ t(`criteria.publish.scopes.${scope}.title`) }}</li>
      <li v-if="scope">{{ t('criteria.publish.affectedOpen', { count: affected }, affected) }}</li>
    </ul>
    <BaseButton variant="primary" :to="{ name: ROUTE_NAMES.ADMIN_CRITERIA }">
      {{ t('criteria.review.backToCriteria') }}
    </BaseButton>
  </section>
</template>
