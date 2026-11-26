<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '@/components/feedback/EmptyState.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { CTA_KINDS, EMPTY_STATE_ICONS } from '@/constants/insights'
import { ROUTE_NAMES } from '@/constants/routeNames'

const props = defineProps({
  emptyState: { type: Object, required: true },
})

const { t } = useI18n()
const base = computed(() => `insights.empty.${props.emptyState.reason}`)
const cta = computed(() => props.emptyState.cta ?? null)
const target = computed(() =>
  cta.value?.kind === CTA_KINDS.VIEW_REQUEST
    ? { name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_FORM, params: { id: cta.value.assessmentId } }
    : { name: ROUTE_NAMES.ASSESSMENT_ACCREDITED },
)
</script>

<template>
  <div class="insights-empty" data-testid="insights-empty" :data-reason="emptyState.reason">
    <EmptyState
      :icon="EMPTY_STATE_ICONS[emptyState.reason] ?? 'flag'"
      :title="t(`${base}.title`)"
      :message="t(`${base}.message`)"
    >
      <template v-if="cta" #action>
        <router-link class="btn btn--primary" :to="target">
          {{ t(`insights.empty.cta.${cta.kind}`) }}
          <AppIcon name="arrow-right" />
        </router-link>
      </template>
    </EmptyState>
    <p class="insights-empty__note">{{ t('insights.empty.note') }}</p>
  </div>
</template>
