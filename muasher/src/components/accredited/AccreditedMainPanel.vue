<script setup>
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import AccreditedSubPanel from './AccreditedSubPanel.vue'
import { localizedText } from '@/utils/localizedText'

defineProps({
  main: { type: Object, required: true },
  workspace: { type: Object, required: true },
})

const emit = defineEmits(['upload', 'remove', 'replace', 'download'])
const { locale } = useI18n()
</script>

<template>
  <Card class="accredited-main">
    <template #header>
      <h2 class="accredited-main__title">
        <span class="accredited-main__code u-ltr">{{ main.code }}</span>
        {{ localizedText(main.name, locale) }}
      </h2>
    </template>
    <p v-if="main.description" class="accredited-main__description">
      {{ localizedText(main.description, locale) }}
    </p>
    <ul class="accredited-main__subs">
      <AccreditedSubPanel
        v-for="sub in main.subs"
        :key="sub.id"
        :sub="sub"
        :workspace="workspace"
        @upload="emit('upload', $event)"
        @remove="emit('remove', $event)"
        @replace="emit('replace', $event)"
        @download="emit('download', $event)"
      />
    </ul>
  </Card>
</template>
