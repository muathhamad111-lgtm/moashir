<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { BRAND_PREVIEW_SURFACES } from '@/constants/branding'
import Card from '@/components/ui/Card.vue'
import Tabs from '@/components/ui/Tabs.vue'
import BrandPreviewBackOffice from '@/components/branding/BrandPreviewBackOffice.vue'
import BrandPreviewPortal from '@/components/branding/BrandPreviewPortal.vue'

const { t } = useI18n()

const surface = ref(BRAND_PREVIEW_SURFACES[0])
const tabs = computed(() =>
  BRAND_PREVIEW_SURFACES.map((value) => ({
    value,
    label: t(`branding.preview.surfaces.${value}`),
  })),
)
</script>

<template>
  <Card :title="t('branding.preview.title')" class="brand-preview">
    <Tabs v-model="surface" :tabs="tabs">
      <div
        class="brand-preview__stage"
        role="img"
        :aria-label="
          t('branding.preview.label', { surface: t(`branding.preview.surfaces.${surface}`) })
        "
      >
        <BrandPreviewBackOffice v-if="surface === 'backOffice'" />
        <BrandPreviewPortal v-else />
      </div>
    </Tabs>
    <p class="brand-preview__note">{{ t('branding.preview.note') }}</p>
  </Card>
</template>
