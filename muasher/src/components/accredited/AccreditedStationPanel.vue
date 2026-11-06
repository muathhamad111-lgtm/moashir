<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import AccreditedSubPanel from './AccreditedSubPanel.vue'
import { formatNumber } from '@/utils/formatNumber'
import { localizedText } from '@/utils/localizedText'

defineProps({
  main: { type: Object, required: true },
  station: { type: Object, required: true },
  workspace: { type: Object, required: true },
})

const emit = defineEmits(['upload', 'remove', 'replace', 'download'])
const { t, n, locale } = useI18n()
</script>

<template>
  <section class="station-panel" :aria-labelledby="`station-${main.id}`">
    <header class="station-panel__head">
      <div class="station-panel__heading">
        <h2 :id="`station-${main.id}`" class="station-panel__title">
          {{ t('portal.assessments.task.criterion') }}
          <bdi dir="ltr">{{ main.code }}</bdi>
          · {{ localizedText(main.name, locale) }}
        </h2>
        <p v-if="main.description" class="station-panel__description">
          {{ localizedText(main.description, locale) }}
        </p>
      </div>
      <Badge variant="warning">
        {{
          t('portal.assessments.task.subsOf', {
            done: formatNumber(n, station.complete),
            total: formatNumber(n, station.total),
          })
        }}
      </Badge>
    </header>
    <ul class="station-panel__subs">
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
  </section>
</template>
