<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'

const props = defineProps({
  records: { type: Array, required: true },
})

const { t } = useI18n()

function foundLabel(record) {
  return record.value ? t('integrations.email.dns.foundPolicy', { policy: record.value }) : t('integrations.email.dns.found')
}

function stateOf(record) {
  if (!record.checked) return { variant: 'muted', label: t(`integrations.email.dns.${record.record === 'dkim' ? 'providerCheck' : 'notChecked'}`) }
  return record.ok ? { variant: 'success', label: foundLabel(record) } : { variant: 'warning', label: t('integrations.email.dns.missing') }
}

const rows = computed(() => props.records.map((record) => ({ ...record, state: stateOf(record) })))
</script>

<template>
  <section class="dns-checklist" :aria-label="t('integrations.email.dns.title')">
    <h3 class="dns-checklist__title">
      <i class="fa-solid fa-globe" aria-hidden="true"></i>
      {{ t('integrations.email.dns.title') }}
    </h3>
    <p class="field__hint">{{ t('integrations.email.dns.hint') }}</p>
    <ul class="dns-checklist__list">
      <li v-for="row in rows" :key="row.record" class="dns-checklist__item">
        <span class="dns-checklist__name">{{ t(`integrations.email.dns.records.${row.record}.name`) }}</span>
        <span class="dns-checklist__desc">{{ t(`integrations.email.dns.records.${row.record}.desc`) }}</span>
        <Badge :variant="row.state.variant">{{ row.state.label }}</Badge>
      </li>
    </ul>
  </section>
</template>
