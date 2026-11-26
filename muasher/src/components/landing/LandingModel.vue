<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLandingText } from '@/composables/landing/useLandingText'
import { useModelCriteria } from '@/composables/useModelCriteria'
import { formatPadded } from '@/utils/formatNumber'
import LandingSectionHead from '@/components/landing/LandingSectionHead.vue'
import LandingGlyph from '@/components/landing/LandingGlyph.vue'

const props = defineProps({
  section: { type: Object, required: true },
})

const { n, t } = useI18n()
const { text, heading } = useLandingText()
const head = computed(() => heading(props.section))
const model = useModelCriteria()

const loading = computed(() => model.status.value === 'loading')
const showsCriteria = computed(() => loading.value || model.status.value === 'success')
const groups = computed(() =>
  [
    { key: 'enablers', items: model.enablers.value },
    { key: 'creativity', items: model.creativity.value },
    { key: 'results', items: model.results.value },
  ].filter((group) => group.items.length),
)
</script>

<template>
  <section class="lp-sec lp-model" aria-labelledby="lp-model-title">
    <div class="lp-wrap">
      <LandingSectionHead
        center
        title-id="lp-model-title"
        :eyebrow="head.eyebrow"
        :title="head.title"
        :highlight="head.highlight"
        :lede="head.lede"
      />
      <ol class="lp-cycle">
        <li
          v-for="(item, index) in section.items"
          :key="item.id"
          v-reveal
          class="lp-cycle__step lp-reveal lp-draw"
          :style="{ '--i': index }"
        >
          <span class="lp-cycle__icon"><LandingGlyph :name="item.meta?.glyph" /></span>
          <span class="lp-cycle__num" dir="ltr" aria-hidden="true">{{
            formatPadded(n, index + 1)
          }}</span>
          <b>{{ text(item.title) }}</b>
          <p>{{ text(item.description) }}</p>
        </li>
      </ol>
      <section
        v-if="showsCriteria"
        class="lp-model-criteria"
        :class="{ 'is-loading': loading }"
        :aria-busy="loading || undefined"
        aria-labelledby="lp-model-criteria-title"
      >
        <h3 id="lp-model-criteria-title" class="lp-model-criteria__title">
          {{ t('public.model.criteriaTitle') }}
        </h3>
        <div v-if="!loading" class="lp-model-criteria__groups">
          <div
            v-for="(group, index) in groups"
            :key="group.key"
            v-reveal
            class="lp-model-criteria__group lp-reveal"
            :style="{ '--i': index }"
          >
            <h4 class="lp-model-criteria__name">{{ t(`public.model.${group.key}`) }}</h4>
            <p class="lp-model-criteria__hint">{{ t(`public.model.${group.key}Hint`) }}</p>
            <ol class="lp-tags lp-model-criteria__list">
              <li v-for="item in group.items" :key="item.id" class="lp-tag">
                {{ model.localized(item.name) }}
              </li>
            </ol>
          </div>
        </div>
      </section>
    </div>
  </section>
</template>
