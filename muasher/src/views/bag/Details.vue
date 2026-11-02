<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import BagIntro from '@/components/bag/BagIntro.vue'
import { bagService } from '@/services/bagService'
import { useAuthStore } from '@/stores/auth'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { locale } = useI18n()
const router = useRouter()
const auth = useAuthStore()

function localized(content) {
  if (content === null || content === undefined) return ''
  if (typeof content === 'string') return content
  return content[locale.value] || content.ar || content.en || ''
}

const status = ref('loading')
const errorMessage = ref('')
const product = ref(null)
const pricing = computed(() => product.value?.pricing ?? null)

async function load() {
  status.value = 'loading'
  errorMessage.value = ''
  try {
    const { data } = await bagService.getProduct()
    product.value = data
    status.value = 'success'
  } catch (error) {
    errorMessage.value = error?.normalized?.message ?? ''
    status.value = 'error'
  }
}

onMounted(load)

function goToPay() {
  if (!auth.isAuthenticated) {
    router.push({ name: ROUTE_NAMES.AUTH_LOGIN, query: { redirect: '/excellence-bag' } })
    return
  }
  router.push({ name: ROUTE_NAMES.BAG_PAY })
}
</script>

<template>
  <section class="bag-details">
    <div v-if="status === 'loading'" class="bag-details__loading">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="5" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />

    <BagIntro
      v-else-if="product"
      :product="product"
      :pricing="pricing"
      :localized="localized"
      :eligible="true"
      @pay="goToPay"
    />
  </section>
</template>
