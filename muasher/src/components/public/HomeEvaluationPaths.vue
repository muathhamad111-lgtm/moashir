<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import IllustrationSlot from '@/components/public/IllustrationSlot.vue'

const props = defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const router = useRouter()
const { t } = useI18n()

function altOf(item) {
  return props.localized(item.title) || t('illustrations.path')
}

const ACCENTS = ['calm', 'accredited', 'award']

const paths = computed(() => [...(props.section.items ?? [])].sort((a, b) => a.order - b.order))

function accentOf(item) {
  const accent = item.meta?.accent
  return ACCENTS.includes(accent) ? accent : 'calm'
}

function pointsOf(item) {
  return Array.isArray(item.meta?.points) ? item.meta.points : []
}

function activate(item) {
  const url = item.ctaUrl
  if (!url) return
  if (url.startsWith('#')) {
    const target = document.getElementById(url.slice(1))
    if (target) target.scrollIntoView({ behavior: 'smooth' })
    return
  }
  if (/^https?:\/\//.test(url)) {
    window.open(url, '_blank', 'noopener')
    return
  }
  router.push(url)
}
</script>

<template>
  <div class="home-section home-paths">
    <div class="home-section__container">
      <header class="home-section__head">
        <h2 class="home-section__title">{{ localized(section.title) }}</h2>
        <p v-if="section.subtitle" class="home-section__subtitle">
          {{ localized(section.subtitle) }}
        </p>
        <p v-if="section.description" class="home-section__description">
          {{ localized(section.description) }}
        </p>
      </header>

      <div class="home-paths__grid">
        <article
          v-for="path in paths"
          :key="path.id"
          class="home-path"
          :class="`home-path--${accentOf(path)}`"
        >
          <IllustrationSlot
            v-if="path.image"
            class="home-path__media"
            :src="path.image"
            :alt="altOf(path)"
            variant="compact"
          />
          <span class="home-path__icon" aria-hidden="true">
            <i :class="path.icon || 'fa-solid fa-flag'"></i>
          </span>
          <h3 class="home-path__title">{{ localized(path.title) }}</h3>
          <p class="home-path__summary">{{ localized(path.description) }}</p>

          <ul
            v-if="pointsOf(path).length"
            class="home-path__points"
            :aria-label="$t('public.home.paths.features')"
          >
            <li v-for="(point, index) in pointsOf(path)" :key="index" class="home-path__point">
              <i class="home-path__point-icon fa-solid fa-check" aria-hidden="true"></i>
              <span>{{ localized(point) }}</span>
            </li>
          </ul>

          <div v-if="path.ctaLabel" class="home-path__cta">
            <BaseButton variant="primary" icon="fa-solid fa-arrow-right" @click="activate(path)">
              {{ localized(path.ctaLabel) }}
            </BaseButton>
          </div>
        </article>
      </div>
    </div>
  </div>
</template>
