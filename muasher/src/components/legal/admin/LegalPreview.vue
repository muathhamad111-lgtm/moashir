<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import RichTextBlocks from '@/components/legal/RichTextBlocks.vue'
import { markdownBlocks } from '@/utils/legal/markdownBlocks'

const DIRECTIONS = { ar: 'rtl', en: 'ltr' }

const props = defineProps({
  locale: { type: String, required: true },
  title: { type: String, default: '' },
  body: { type: String, default: '' },
})

const { t } = useI18n()
const blocks = computed(() => markdownBlocks(props.body))
</script>

<template>
  <article class="legal-preview" :lang="locale" :dir="DIRECTIONS[locale]">
    <p class="legal-preview__label">{{ t(`legalAdmin.preview.${locale}`) }}</p>
    <h3 class="legal-preview__title">{{ title || t('legalAdmin.preview.noTitle') }}</h3>
    <RichTextBlocks v-if="blocks.length" :blocks="blocks" :heading-base="4" />
    <p v-else class="legal-preview__empty">{{ t('legalAdmin.preview.empty') }}</p>
  </article>
</template>
