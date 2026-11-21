<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import AccreditedSubPanel from './AccreditedSubPanel.vue'
import AccreditedMainEvidence from './AccreditedMainEvidence.vue'
import CriterionStepCounter from '@/components/assessment/CriterionStepCounter.vue'
import { formatNumber } from '@/utils/formatNumber'
import { localizedText } from '@/utils/localizedText'

defineProps({
  main: { type: Object, required: true },
  station: { type: Object, required: true },
  step: { type: Number, required: true },
  total: { type: Number, required: true },
  workspace: { type: Object, required: true },
})

const emit = defineEmits(['upload', 'remove', 'replace', 'download'])
const { t, n, locale } = useI18n()
</script>

<template>
  <section class="station-panel" :aria-labelledby="`station-${main.id}`">
    <header class="station-panel__head">
      <div class="station-panel__heading">
        <CriterionStepCounter :step="step" :total="total" />
        <h2 :id="`station-${main.id}`" class="station-panel__title">
          {{ localizedText(main.name, locale) }}
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
    <AccreditedMainEvidence
      v-if="main.attachments?.length"
      :main="main"
      :workspace="workspace"
      @upload="emit('upload', $event)"
      @remove="emit('remove', $event)"
      @replace="emit('replace', $event)"
      @download="emit('download', $event)"
    />
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
