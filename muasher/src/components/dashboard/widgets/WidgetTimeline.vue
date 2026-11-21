<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import PathBadge from '@/components/ui/PathBadge.vue'
import { formatRiyadhDateTime } from '@/utils/datetime'
import { statusLabelKey } from '@/utils/canonicalStatus'

const props = defineProps({
  data: { type: [Array, Object, null], default: null },
})

const { t, te, locale } = useI18n()

function labelOr(key, fallback) {
  return te(key) ? t(key) : fallback
}

const items = computed(() =>
  (Array.isArray(props.data) ? props.data : []).map((item, index) => ({
    id: `${item.assessmentId}-${item.at}-${index}`,
    number: item.number,
    path: item.path,
    pathLabel: item.pathLabel ?? null,
    verb: labelOr(`dashboard.verbs.${item.verb}`, item.verb),
    status: labelOr(statusLabelKey(item.toStatus), item.toStatus),
    at: item.at,
    time: formatRiyadhDateTime(item.at, locale.value),
  })),
)
</script>

<template>
  <p v-if="!items.length" class="dashboard-widget__empty">{{ t('dashboard.widgets.empty') }}</p>
  <ol v-else class="widget-timeline">
    <li v-for="item in items" :key="item.id" class="widget-timeline__item">
      <p class="widget-timeline__head">
        <bdi class="widget-timeline__number" dir="ltr">{{ item.number }}</bdi>
        <PathBadge v-if="item.path" :path="item.path" :label="item.pathLabel" compact />
      </p>
      <p class="widget-timeline__text">
        {{ t('dashboard.widgets.transition', { verb: item.verb, status: item.status }) }}
      </p>
      <time class="widget-timeline__time" :datetime="item.at">{{ item.time }}</time>
    </li>
  </ol>
</template>
