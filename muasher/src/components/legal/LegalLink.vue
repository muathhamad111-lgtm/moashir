<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLegalDocumentsStore } from '@/stores/legalDocuments'
import { LEGAL_STATES } from '@/constants/legal'
import { ROUTE_NAMES } from '@/constants/routeNames'

const ROUTES = {
  terms: ROUTE_NAMES.LEGAL_TERMS,
  privacy: ROUTE_NAMES.LEGAL_PRIVACY,
  accessibility: ROUTE_NAMES.LEGAL_ACCESSIBILITY,
}

const props = defineProps({
  kind: { type: String, required: true },
  newTab: { type: Boolean, default: false },
  linkClass: { type: String, default: '' },
  soonClass: { type: String, default: 'legal-link__soon' },
})

const { t } = useI18n()
const store = useLegalDocumentsStore()
const state = computed(() => store.stateOf(props.kind))
const label = computed(() => t(`legal.names.${props.kind}`))
const linkClasses = computed(() => props.linkClass || 'legal-link')
const plainClasses = computed(() => props.linkClass || 'legal-link legal-link--plain')

onMounted(() => store.load(props.kind))
</script>

<template>
  <router-link
    v-if="state === LEGAL_STATES.AVAILABLE"
    :class="linkClasses"
    :to="{ name: ROUTES[kind] }"
    :target="newTab ? '_blank' : undefined"
    :rel="newTab ? 'noopener' : undefined"
    >{{ label
    }}<span v-if="newTab" class="legal-link__sr">{{ t('legal.newTab') }}</span></router-link
  >
  <span v-else :class="plainClasses">
    {{ label }}
    <span v-if="state !== LEGAL_STATES.LOADING" :class="soonClass">{{
      t('public.home.comingSoon')
    }}</span>
  </span>
</template>
