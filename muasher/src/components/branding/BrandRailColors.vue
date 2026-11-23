<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { BRAND_RAIL_ROLES } from '@/constants/branding'
import { useAdoptedStyleSheet } from '@/composables/useAdoptedStyleSheet'
import { railSuggestions } from '@/utils/brandRail'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BrandRailField from '@/components/branding/BrandRailField.vue'

const props = defineProps({
  rail: { type: Object, required: true },
  autoRail: { type: Object, default: null },
  scheme: { type: Object, default: null },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['set', 'restore-auto', 'swap', 'use-pair'])
const { t } = useI18n()

const ideas = computed(() => (props.scheme ? railSuggestions(props.scheme) : []))

function swatchCss(idea) {
  const top = idea.top ?? props.autoRail?.top
  const bottom = idea.bottom ?? props.autoRail?.bottom
  if (!top || !bottom) return ''
  return `.brand-rail__idea[data-idea="${idea.id}"] .brand-rail__swatch{background:linear-gradient(180deg,${top},${bottom})}`
}

// Each suggestion's swatch, through CSSOM: the production CSP refuses inline style.
useAdoptedStyleSheet(computed(() => ideas.value.map(swatchCss).join('')))
</script>

<template>
  <Card :title="t('branding.rail.title')" class="brand-rail">
    <p class="brand-colors__hint">{{ t('branding.rail.hint') }}</p>
    <div class="brand-rail__layout">
      <div class="brand-rail__fields">
        <BrandRailField
          v-for="role in BRAND_RAIL_ROLES"
          :key="role"
          :role="role"
          :model-value="rail[role]"
          :auto-value="autoRail?.[role] ?? ''"
          :disabled="disabled"
          @update:model-value="emit('set', role, $event)"
          @restore-auto="emit('restore-auto', role)"
        />
        <BaseButton
          class="brand-rail__swap"
          variant="ghost"
          size="sm"
          icon="swap"
          :disabled="disabled"
          @click="emit('swap')"
        >
          {{ t('branding.rail.swap') }}
        </BaseButton>
      </div>
      <div class="brand-rail__sample" role="img" :aria-label="t('branding.rail.sample')">
        <span class="brand-rail__sample-item">{{
          t('branding.preview.sample.nav.dashboard')
        }}</span>
        <span class="brand-rail__sample-item brand-rail__sample-item--active">
          {{ t('branding.preview.sample.nav.requests') }}
        </span>
        <span class="brand-rail__sample-item brand-rail__sample-item--muted">
          {{ t('branding.preview.sample.nav.entities') }}
        </span>
        <span class="brand-rail__sample-item brand-rail__sample-item--muted">
          {{ t('branding.preview.sample.nav.settings') }}
        </span>
      </div>
    </div>
    <div v-if="ideas.length" class="brand-rail__ideas">
      <p class="brand-rail__ideas-title">{{ t('branding.rail.ideas.title') }}</p>
      <div class="brand-rail__ideas-row">
        <button
          v-for="idea in ideas"
          :key="idea.id"
          type="button"
          class="brand-rail__idea"
          :data-idea="idea.id"
          :disabled="disabled"
          @click="emit('use-pair', idea)"
        >
          <span class="brand-rail__swatch" aria-hidden="true" />
          {{ t(`branding.rail.ideas.${idea.id}`) }}
        </button>
      </div>
    </div>
  </Card>
</template>
