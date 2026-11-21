<script setup>
import { useI18n } from 'vue-i18n'
import { localizedText } from '@/utils/localizedText'
import { formatRiyadhDate } from '@/utils/datetime'
import { formatNumber } from '@/utils/formatNumber'

// The last step of a completion request (design §B.3.3): what the entity will receive, before it is
// sent: the items with their notes, the message, the deadline and what happens when it passes.
defineProps({
  options: { type: Array, required: true },
  notes: { type: Object, required: true },
  message: { type: String, default: '' },
  deadline: { type: String, default: '' },
  outcomeLabel: { type: String, default: '' },
})

const { t, n, locale } = useI18n()

function labelOf(option) {
  if (!option.sub)
    return t('review.completion.mainItem', { main: localizedText(option.main.name, locale.value) })
  return t('review.completion.itemCell', {
    sub: localizedText(option.sub.name, locale.value),
    kind: t(`accredited.completion.kinds.${option.kind}`),
  })
}
</script>

<template>
  <div class="completion-review">
    <section class="completion-review__block">
      <h3 class="completion-review__title">
        {{ t('review.completion.selectedTitle', { count: formatNumber(n, options.length) }) }}
      </h3>
      <ul class="completion-review__items">
        <li v-for="option in options" :key="option.key" class="completion-review__item">
          <span class="completion-review__label">{{ labelOf(option) }}</span>
          <span v-if="notes[option.key]" class="completion-review__note">{{
            notes[option.key]
          }}</span>
        </li>
      </ul>
    </section>
    <dl class="completion-review__facts">
      <div class="completion-review__fact">
        <dt>{{ t('review.completion.notes') }}</dt>
        <dd>{{ message }}</dd>
      </div>
      <div class="completion-review__fact">
        <dt>{{ t('review.completion.deadline') }}</dt>
        <dd>
          <bdi>{{ deadline ? formatRiyadhDate(`${deadline}T12:00:00+03:00`, locale) : '—' }}</bdi>
        </dd>
      </div>
      <div class="completion-review__fact">
        <dt>{{ t('review.completion.expiryOutcome') }}</dt>
        <dd>{{ outcomeLabel }}</dd>
      </div>
    </dl>
  </div>
</template>
