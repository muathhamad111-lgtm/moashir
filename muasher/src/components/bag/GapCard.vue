<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  gap: { type: Object, required: true },
  localized: { type: Function, required: true },
  downloadable: { type: Boolean, default: true },
})

const { n } = useI18n()

const gapPercent = computed(() => {
  const target = Number(props.gap.target) || 0
  if (!target) return 0
  const raw =
    props.gap.gapPercent != null
      ? props.gap.gapPercent
      : ((Number(props.gap.gap) || 0) / target) * 100
  return Math.min(100, Math.max(0, Math.round(raw)))
})

const stageStatus = computed(() => (props.gap.open ? 'open' : 'not-yet-available'))

const PRIORITY_VARIANTS = { high: 'danger', medium: 'warning', low: 'info' }
const priorityVariant = computed(() => PRIORITY_VARIANTS[props.gap.priority] ?? 'muted')
</script>

<template>
  <Card class="gap-card">
    <template #header>
      <div class="gap-card__head">
        <div class="gap-card__titles">
          <span class="gap-card__main">{{ localized(gap.mainName) }}</span>
          <h3 class="gap-card__sub">{{ localized(gap.subName) }}</h3>
        </div>
        <div class="gap-card__badges">
          <Badge v-if="gap.priority" :variant="priorityVariant">
            {{ $t('bag.gap.priority.label', { level: $t(`bag.gap.priority.${gap.priority}`) }) }}
          </Badge>
          <StatusBadge :status="stageStatus" />
        </div>
      </div>
    </template>

    <div class="gap-card__scores">
      <div class="gap-card__score">
        <span class="gap-card__score-label">{{ $t('bag.gap.current') }}</span>
        <span class="gap-card__score-value">{{ n(gap.current, 'default') }}</span>
      </div>
      <div class="gap-card__score">
        <span class="gap-card__score-label">{{ $t('bag.gap.target') }}</span>
        <span class="gap-card__score-value">{{ n(gap.target, 'default') }}</span>
      </div>
      <div class="gap-card__score gap-card__score--gap">
        <span class="gap-card__score-label">{{ $t('bag.gap.gap') }}</span>
        <span class="gap-card__score-value">{{ n(gap.gap, 'default') }}</span>
      </div>
    </div>

    <div
      class="gap-card__bar"
      role="progressbar"
      :aria-valuenow="gapPercent"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-label="$t('bag.gap.barLabel', { name: localized(gap.subName) })"
    >
      <span class="gap-card__bar-fill" :style="{ '--gap-fill': `${gapPercent}%` }"></span>
    </div>
    <p class="gap-card__bar-caption">
      {{
        $t('bag.gap.gapOfTarget', { gap: n(gap.gap, 'default'), target: n(gap.target, 'default') })
      }}
    </p>

    <div v-if="gap.consultantNote" class="gap-card__note">
      <AppIcon name="pen" class="gap-card__note-icon" />
      <div>
        <p class="gap-card__note-label">{{ $t('bag.gap.consultantNote') }}</p>
        <p class="gap-card__note-body">{{ localized(gap.consultantNote) }}</p>
      </div>
    </div>

    <div v-if="gap.recommendedAction" class="gap-card__action">
      <AppIcon name="lightbulb" class="gap-card__action-icon" />
      <div>
        <p class="gap-card__action-label">{{ $t('bag.gap.recommendedAction') }}</p>
        <p class="gap-card__action-body">{{ localized(gap.recommendedAction) }}</p>
      </div>
    </div>

    <div v-if="gap.unmetPoints && gap.unmetPoints.length" class="gap-card__section">
      <h4 class="gap-card__section-title">{{ $t('bag.gap.unmetPoints') }}</h4>
      <ul class="gap-card__points">
        <li v-for="point in gap.unmetPoints" :key="point.id" class="gap-card__point">
          <AppIcon name="warning-triangle" class="gap-card__point-icon" />
          <span>{{ localized(point.description) }}</span>
        </li>
      </ul>
    </div>

    <div v-if="gap.helperFiles && gap.helperFiles.length" class="gap-card__section">
      <h4 class="gap-card__section-title">{{ $t('bag.gap.helperFiles') }}</h4>
      <ul class="gap-card__files">
        <li v-for="file in gap.helperFiles" :key="file.id" class="gap-card__file">
          <AppIcon name="download" class="gap-card__file-icon" />
          <a
            v-if="downloadable"
            class="gap-card__file-link"
            :href="file.url"
            :download="file.fileName"
            >{{ localized(file.name) }}</a
          >
          <span v-else class="gap-card__file-name">{{ localized(file.name) }}</span>
          <span class="gap-card__file-size">
            {{ $t('bag.gap.fileSize', { size: n(file.sizeKb, 'default') }) }}
          </span>
        </li>
      </ul>
    </div>
    <p v-else-if="downloadable" class="gap-card__no-files">{{ $t('bag.gap.noFiles') }}</p>
  </Card>
</template>
