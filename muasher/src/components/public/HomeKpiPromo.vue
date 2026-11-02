<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import KpiCard from '@/components/kpis/KpiCard.vue'
import { kpisService } from '@/services/kpisService'

const props = defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const { locale } = useI18n()
const router = useRouter()

const featured = ref([])

const stats = computed(() =>
  [...(props.section.items ?? [])].sort((a, b) => a.order - b.order),
)

function cardLocalized(content) {
  if (content === null || content === undefined) return ''
  if (typeof content === 'string') return content
  return content[locale.value] || content.ar || content.en || ''
}

async function loadFeatured() {
  try {
    const { data } = await kpisService.getFeatured()
    featured.value = Array.isArray(data) ? data.slice(0, 6) : []
  } catch {
    featured.value = []
  }
}

onMounted(loadFeatured)

function goToCta() {
  const url = props.section.ctaUrl
  if (!url) return
  if (/^https?:\/\//.test(url)) {
    window.open(url, '_blank', 'noopener')
    return
  }
  router.push(url)
}

function goToDetail(kpi) {
  if (kpi.slug && router.hasRoute('public.kpis.detail')) {
    router.push({ name: 'public.kpis.detail', params: { slug: kpi.slug } })
  }
}
</script>

<template>
  <div class="home-section home-kpi-promo">
    <div class="home-section__container">
      <div class="home-kpi-promo__panel">
        <span class="home-kpi-promo__icon" aria-hidden="true">
          <i :class="section.icon || 'fa-solid fa-chart-column'"></i>
        </span>
        <div class="home-kpi-promo__content">
          <header class="home-section__head home-kpi-promo__head">
            <h2 class="home-section__title">{{ localized(section.title) }}</h2>
            <p v-if="section.subtitle" class="home-section__subtitle">
              {{ localized(section.subtitle) }}
            </p>
            <p v-if="section.description" class="home-section__description">
              {{ localized(section.description) }}
            </p>
          </header>

          <ul v-if="stats.length" class="home-kpi-promo__stats">
            <li v-for="stat in stats" :key="stat.id" class="home-kpi-promo__stat">
              <span class="home-kpi-promo__stat-title">{{ localized(stat.title) }}</span>
              <span v-if="stat.description" class="home-kpi-promo__stat-desc">
                {{ localized(stat.description) }}
              </span>
            </li>
          </ul>

          <div v-if="featured.length" class="home-kpi-promo__preview">
            <h3 class="home-kpi-promo__preview-title">{{ $t('kpis.homePreview.title') }}</h3>
            <ul class="home-kpi-promo__grid">
              <li v-for="kpi in featured" :key="kpi.id" class="home-kpi-promo__cell">
                <KpiCard :kpi="kpi" :localized="cardLocalized" @activate="goToDetail" />
              </li>
            </ul>
          </div>

          <div v-if="section.ctaLabel" class="home-kpi-promo__cta">
            <BaseButton variant="primary" icon="fa-solid fa-arrow-right" @click="goToCta">
              {{ localized(section.ctaLabel) }}
            </BaseButton>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
