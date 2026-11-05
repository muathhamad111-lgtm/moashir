<script setup>
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import CycleDateList from './CycleDateList.vue'
import CycleRegionList from './CycleRegionList.vue'
import { localizedText } from '@/utils/localizedText'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  status: { type: String, required: true },
  cycle: { type: Object, default: null },
})

const emit = defineEmits(['retry'])
const { t, locale } = useI18n()
</script>

<template>
  <Card class="public-cycle" :title="t('advanced.cycle.title')">
    <div v-if="status === 'loading'" class="public-cycle__loading" aria-busy="true">
      <Skeleton variant="text" :count="4" />
    </div>
    <ErrorState
      v-else-if="status === 'error'"
      :message="t('advanced.cycle.error')"
      @retry="emit('retry')"
    />
    <EmptyState
      v-else-if="!cycle"
      icon="calendar"
      :title="t('advanced.cycle.noneTitle')"
      :message="t('advanced.cycle.noneMessage')"
    />
    <div v-else class="public-cycle__body">
      <div class="public-cycle__head">
        <h2 class="public-cycle__name">
          {{ localizedText(cycle.name, locale) }} <bdi class="u-ltr">{{ cycle.year }}</bdi>
        </h2>
        <Badge :variant="cycle.isAcceptingApplications ? 'success' : 'muted'">
          {{
            cycle.isAcceptingApplications
              ? t('advanced.cycle.accepting')
              : t('advanced.cycle.notAccepting')
          }}
        </Badge>
      </div>
      <CycleDateList :cycle="cycle" />
      <CycleRegionList :regions="cycle.regions" />
      <p class="public-cycle__condition">
        <AppIcon name="refresh-dots" />
        {{ t('advanced.cycle.minAge', { years: cycle.conditions.minEntityAgeYears }) }}
      </p>
    </div>
  </Card>
</template>
