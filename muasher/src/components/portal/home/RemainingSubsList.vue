<script setup>
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'

defineProps({
  items: { type: Array, required: true },
})

const { t, n, locale } = useI18n()

function nameOf(item) {
  return item.name?.[locale.value] || item.name?.ar || item.name?.en || ''
}
</script>

<template>
  <section class="remaining-subs" aria-labelledby="remaining-subs-title">
    <h3 id="remaining-subs-title" class="remaining-subs__title">
      {{ t('portal.home.next.remaining.title') }}
    </h3>
    <ul class="remaining-subs__list">
      <li v-for="item in items" :key="item.subCriterionId" class="remaining-subs__item">
        <span class="remaining-subs__dot" aria-hidden="true"></span>
        <span class="remaining-subs__name">
          <bdi class="remaining-subs__code" dir="ltr">{{ item.code }}</bdi>
          {{ nameOf(item) }}
        </span>
        <bdi class="remaining-subs__count" dir="ltr">{{
          t('portal.home.range', {
            done: formatNumber(n, item.attachmentsUploaded),
            total: formatNumber(n, item.attachmentsRequired),
          })
        }}</bdi>
      </li>
    </ul>
    <p class="remaining-subs__note">{{ t('portal.home.next.remaining.note') }}</p>
  </section>
</template>
