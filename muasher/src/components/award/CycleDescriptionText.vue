<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { localizedText } from '@/utils/localizedText'
import { descriptionParagraphs } from '@/utils/award/cycleSetup'

const props = defineProps({
  cycle: { type: Object, default: null },
})

const { t, locale } = useI18n()
const paragraphs = computed(() =>
  descriptionParagraphs(localizedText(props.cycle?.description, locale.value)),
)
const cycleName = computed(() => localizedText(props.cycle?.name, locale.value))
</script>

<template>
  <section
    v-if="paragraphs.length"
    class="cycle-description"
    data-testid="cycle-description"
    :aria-label="t('cycles.setup.identity.description')"
  >
    <h2 class="cycle-description__title">{{ cycleName }}</h2>
    <p
      v-for="(paragraph, index) in paragraphs"
      :key="`${index}:${paragraph}`"
      class="cycle-description__text"
    >
      {{ paragraph }}
    </p>
  </section>
</template>
