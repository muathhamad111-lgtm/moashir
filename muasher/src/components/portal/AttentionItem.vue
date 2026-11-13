<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'
import { percentText } from '@/utils/percentText'
import { formatRelativeTime } from '@/utils/datetime'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  view: { type: Object, required: true },
  layout: {
    type: String,
    default: 'chip',
    validator: (value) => ['chip', 'row'].includes(value),
  },
})

const { t, n, locale } = useI18n()

const due = computed(() =>
  props.view.dueAt
    ? t('portal.strip.due', { time: formatRelativeTime(props.view.dueAt, locale.value) })
    : '',
)
</script>

<template>
  <router-link
    v-if="view.to"
    class="attention-item"
    :class="[`attention-item--${layout}`, `attention-item--${view.tone}`]"
    :to="view.to"
  >
    <AppIcon :name="view.icon" class="attention-item__icon" />
    <span class="attention-item__text">
      <i18n-t :keypath="view.textKey" :plural="view.plural" scope="global" tag="span">
        <template #count>{{ formatNumber(n, view.plural) }}</template>
        <template #reference>
          <bdi class="attention-item__reference" dir="ltr">{{ view.reference }}</bdi>
        </template>
        <template #percent>
          <bdi dir="ltr">{{ percentText(n, t, view.percent) }}</bdi>
        </template>
      </i18n-t>
      <span v-if="due" class="attention-item__due">{{ due }}</span>
    </span>
    <span class="attention-item__open">
      {{ t('portal.strip.open') }}
      <AppIcon name="chevron-right" />
    </span>
  </router-link>
</template>
