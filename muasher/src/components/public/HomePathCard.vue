<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PATH_ACCENTS } from '@/constants/homeSections'
import { formatNumber } from '@/utils/formatNumber'
import BaseButton from '@/components/ui/BaseButton.vue'
import HomePill from '@/components/public/HomePill.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const UNAVAILABLE = ['coming_soon', 'demo']

const PILLS = {
  calm: { tone: 'free', key: 'public.home.paths.badges.free' },
  accredited: {
    tone: 'on-dark',
    key: 'public.home.paths.badges.available',
    icon: 'star',
  },
  award: { tone: 'gold', key: 'public.home.paths.badges.soon' },
}

const props = defineProps({
  path: { type: Object, required: true },
  localized: { type: Function, required: true },
  price: { type: Number, default: null },
})

const emit = defineEmits(['activate'])

const { n, t } = useI18n()

const accent = computed(() =>
  PATH_ACCENTS.includes(props.path.meta?.accent) ? props.path.meta.accent : PATH_ACCENTS[0],
)
const isComingSoon = computed(() => UNAVAILABLE.includes(props.path.meta?.availability))
const pill = computed(() => (isComingSoon.value ? PILLS.award : PILLS[accent.value]))
const points = computed(() =>
  Array.isArray(props.path.meta?.points) ? props.path.meta.points : [],
)
const showsPrice = computed(() => accent.value === 'accredited' && props.price !== null)
const priceText = computed(() => formatNumber(n, props.price, 'money'))
const ctaVariant = computed(() => (accent.value === 'accredited' ? 'primary' : 'secondary'))
</script>

<template>
  <article class="home-path" :class="`home-path--${accent}`">
    <div class="home-path__top">
      <span class="home-path__icon" aria-hidden="true">
        <AppIcon :name="path.icon || 'flag'" />
      </span>
      <HomePill class="home-path__availability" :tone="pill.tone" :icon="pill.icon || ''">
        {{ t(pill.key) }}
      </HomePill>
    </div>
    <h3 class="home-path__title">{{ localized(path.title) }}</h3>
    <p class="home-path__summary">{{ localized(path.description) }}</p>
    <ul
      v-if="points.length"
      class="home-path__points"
      :aria-label="t('public.home.paths.features')"
    >
      <li v-for="(point, index) in points" :key="index" class="home-path__point">
        <AppIcon name="check-circle" class="home-path__point-icon" />
        <span>{{ localized(point) }}</span>
      </li>
    </ul>
    <p v-if="showsPrice" class="home-path__price">
      <bdi class="home-path__amount" dir="ltr">{{ priceText }}</bdi>
      <span class="home-path__price-note">{{ t('public.home.paths.priceNote') }}</span>
    </p>
    <div class="home-path__cta">
      <BaseButton v-if="isComingSoon" variant="secondary" size="xl" disabled>
        {{ t('public.home.paths.badges.soon') }}
      </BaseButton>
      <BaseButton
        v-else-if="path.ctaLabel"
        :variant="ctaVariant"
        size="xl"
        icon-end="arrow-right"
        @click="emit('activate', path)"
      >
        {{ localized(path.ctaLabel) }}
      </BaseButton>
    </div>
  </article>
</template>
