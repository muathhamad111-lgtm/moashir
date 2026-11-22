<script setup>
import { useI18n } from 'vue-i18n'
import { DERIVED_BRAND_COLORS } from '@/constants/branding'
import Card from '@/components/ui/Card.vue'
import BrandDerivedColorRow from '@/components/branding/BrandDerivedColorRow.vue'

defineProps({
  scheme: { type: Object, default: null },
  autoColors: { type: Object, required: true },
  overrides: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['override', 'restore-auto'])
const { t } = useI18n()
</script>

<template>
  <Card :title="t('branding.derived.title')" class="brand-derived">
    <p class="brand-colors__hint">{{ t('branding.derived.hint') }}</p>
    <ul v-if="scheme" class="brand-derived__list">
      <BrandDerivedColorRow
        v-for="key in DERIVED_BRAND_COLORS"
        :key="key"
        :color-key="key"
        :value="scheme[key]"
        :auto-value="autoColors[key] ?? ''"
        :is-custom="Boolean(overrides[key])"
        :disabled="disabled"
        @override="emit('override', key, $event)"
        @restore-auto="emit('restore-auto', key)"
      />
    </ul>
  </Card>
</template>
