<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { insightsService } from '@/services/insightsService'
import { SUPPORTED_LOCALES } from '@/constants/locales'

const props = defineProps({
  assessmentId: { type: Number, required: true },
})

const { t, locale } = useI18n()
const reportLocale = ref(locale.value)
const downloads = useSignedDownload({
  errorPrefix: 'insights.board.errors',
  fallbackKey: 'insights.board.errors.failed',
})

function download() {
  return downloads.download('board', () =>
    insightsService.exportBoardReport(props.assessmentId, { locale: reportLocale.value }),
  )
}
</script>

<template>
  <section
    class="b-card board-download"
    aria-labelledby="board-download-title"
    data-testid="board-download"
  >
    <h2 id="board-download-title" class="b-card__title">
      {{ t('insights.board.download.title') }}
    </h2>
    <div
      class="board-download__preview"
      role="img"
      :aria-label="t('insights.board.download.preview')"
    >
      <span class="board-download__bar board-download__bar--head"></span>
      <span v-for="line in 7" :key="line" class="board-download__bar"></span>
    </div>
    <p class="board-download__note">{{ t('insights.board.download.note') }}</p>
    <fieldset class="board-download__locales">
      <legend class="board-download__legend">{{ t('insights.board.download.language') }}</legend>
      <label v-for="value in SUPPORTED_LOCALES" :key="value" class="board-download__locale">
        <input v-model="reportLocale" type="radio" name="board-report-locale" :value="value" />
        <span>{{ t(`feedback.report.locales.${value}`) }}</span>
      </label>
    </fieldset>
    <BaseButton
      variant="primary"
      icon="download"
      :loading="Boolean(downloads.busy.board)"
      data-testid="board-download-action"
      @click="download"
    >
      {{ t('insights.board.download.action') }}
    </BaseButton>
  </section>
</template>
