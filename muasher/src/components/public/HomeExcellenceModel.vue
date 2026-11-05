<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import IllustrationSlot from '@/components/public/IllustrationSlot.vue'

const props = defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const { t } = useI18n()

const modelImage = computed(() => props.section.image || null)
const modelAlt = computed(
  () => props.localized(props.section.title) || t('illustrations.excellenceModel'),
)

const BANDS = [
  { key: 'enabler', icon: 'fa-solid fa-gears' },
  { key: 'creativity', icon: 'fa-solid fa-lightbulb' },
  { key: 'result', icon: 'fa-solid fa-chart-line' },
]

const orderedItems = computed(() =>
  [...(props.section.items ?? [])].sort((a, b) => a.order - b.order),
)

const bands = computed(() =>
  BANDS.map((band) => ({
    ...band,
    items: orderedItems.value.filter((item) => item.meta?.band === band.key),
  })).filter((band) => band.items.length),
)
</script>

<template>
  <div class="home-section home-model">
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

      <IllustrationSlot
        v-if="modelImage"
        class="home-model__media"
        :src="modelImage"
        :alt="modelAlt"
        variant="banner"
      />

      <div class="home-model__bands">
        <template v-for="(band, index) in bands" :key="band.key">
          <section
            class="home-model__band"
            :class="`home-model__band--${band.key}`"
            :aria-labelledby="`home-model-band-${band.key}`"
          >
            <header class="home-model__band-head">
              <h3 :id="`home-model-band-${band.key}`" class="home-model__band-title">
                {{ $t(`public.home.model.bands.${band.key}`) }}
              </h3>
              <p class="home-model__band-hint">
                {{ $t(`public.home.model.bands.${band.key}Hint`) }}
              </p>
            </header>

            <ul class="home-model__grid">
              <li
                v-for="criterion in band.items"
                :key="criterion.id"
                class="home-model__chip"
                :class="`home-model__chip--${band.key}`"
                tabindex="0"
              >
                <div class="home-model__chip-head">
                  <span class="home-model__chip-order" aria-hidden="true">
                    {{ criterion.order }}
                  </span>
                  <span v-if="criterion.meta?.weight" class="home-model__chip-weight">
                    {{ $t('public.home.model.weight', { value: criterion.meta.weight }) }}
                  </span>
                </div>
                <h4 class="home-model__chip-title">{{ localized(criterion.title) }}</h4>
                <p v-if="criterion.description" class="home-model__chip-desc">
                  {{ localized(criterion.description) }}
                </p>
              </li>
            </ul>
          </section>

          <div v-if="index < bands.length - 1" class="home-model__arrow" aria-hidden="true">
            <i class="fa-solid fa-arrow-right-long"></i>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
