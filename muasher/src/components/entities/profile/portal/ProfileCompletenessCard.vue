<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { percentText } from '@/utils/percentText'
import AppIcon from '@/components/ui/AppIcon.vue'

const RING_RADIUS = 15.9155
const RING_LENGTH = 100

const props = defineProps({
  completion: { type: Object, default: null },
})

const { t, n } = useI18n()
const percent = computed(() => props.completion?.percent ?? 0)
const items = computed(() => props.completion?.items ?? [])
const dash = computed(() => `${percent.value} ${RING_LENGTH - percent.value}`)

function stateOf(item) {
  if (item.isComplete) return 'complete'
  return item.isRequired ? 'missing' : 'optional'
}
</script>

<template>
  <section class="profile-completeness" aria-labelledby="profile-completeness-title">
    <div class="profile-completeness__head">
      <div class="profile-completeness__ring" role="img" :aria-label="percentText(n, t, percent)">
        <svg viewBox="0 0 36 36" focusable="false" aria-hidden="true">
          <circle class="profile-completeness__track" cx="18" cy="18" :r="RING_RADIUS" />
          <circle
            class="profile-completeness__fill"
            cx="18"
            cy="18"
            :r="RING_RADIUS"
            :stroke-dasharray="dash"
          />
        </svg>
        <bdi class="profile-completeness__value" dir="ltr" aria-hidden="true">{{
          percentText(n, t, percent)
        }}</bdi>
      </div>
      <div>
        <h2 id="profile-completeness-title" class="profile-completeness__title">
          {{ t('portal.profile.completion.title') }}
        </h2>
        <p class="profile-completeness__text">{{ t('portal.profile.completion.text') }}</p>
      </div>
    </div>
    <ul class="profile-completeness__items">
      <li
        v-for="item in items"
        :key="item.key"
        class="profile-completeness__item"
        :class="`profile-completeness__item--${stateOf(item)}`"
      >
        <AppIcon :name="item.isComplete ? 'check-circle' : 'clock'" />
        <span class="profile-completeness__label">{{
          t(`portal.profile.completion.items.${item.key}`)
        }}</span>
        <span class="profile-completeness__state">{{
          t(`portal.profile.completion.states.${stateOf(item)}`)
        }}</span>
      </li>
    </ul>
  </section>
</template>
