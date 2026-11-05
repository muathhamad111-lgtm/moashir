<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import AccreditedPriceCard from '@/components/accredited/AccreditedPriceCard.vue'
import AccreditedCurrentCard from '@/components/accredited/AccreditedCurrentCard.vue'
import { useAccreditedIntro } from '@/composables/useAccreditedIntro'
import { useAccreditedIntroContent } from '@/composables/useIntroPageContent'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'
import DemoDataNotice from '@/components/ui/DemoDataNotice.vue'
import { MOCK_MODULES } from '@/constants/mockModules'
import AppIcon from '@/components/ui/AppIcon.vue'

const PAY_PATH = '/assessment/accredited/pay'

const { t } = useI18n()
const router = useRouter()
const { canAll } = usePermissions()
const { status, errorMessage, current, price, priceStatus, isAuthenticated, isEntity, load } =
  useAccreditedIntro()
const cms = useAccreditedIntroContent()
const { localized, benefits, whyRecommended } = cms
const introTitle = computed(() => cms.title.value || t('accredited.intro.title'))
const introSubtitle = computed(() => cms.subtitle.value || t('accredited.intro.subtitle'))
const introDescription = computed(() => cms.description.value)
const feesCopy = computed(() => cms.fees.value)
const paymentCopy = computed(() => cms.payment.value)
const canPay = computed(() =>
  canAll([PERMISSIONS.PAYMENTS_OWN_CREATE, PERMISSIONS.ASSESSMENT_ACCREDITED_MANAGE]),
)

const action = computed(() => {
  if (!isAuthenticated.value)
    return {
      key: 'login',
      icon: 'login',
      to: { name: ROUTE_NAMES.AUTH_LOGIN, query: { redirect: PAY_PATH } },
    }
  if (current.value?.active)
    return {
      key: 'resume',
      icon: 'pen-line',
      to: { name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_FORM, params: { id: current.value.active.id } },
    }
  if (!isEntity.value || !canPay.value) return null
  if (current.value?.awaitingPayment)
    return {
      key: 'continuePayment',
      icon: 'credit-card',
      to: { name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_PAY },
    }
  return {
    key: 'pay',
    icon: 'credit-card',
    to: { name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_PAY },
  }
})

onMounted(load)

const DEMO_MODULES = [MOCK_MODULES.CMS]
</script>

<template>
  <section class="accredited-intro">
    <header class="accredited-intro__header">
      <p class="accredited-intro__eyebrow">{{ t('accredited.intro.eyebrow') }}</p>
      <h1 class="accredited-intro__title">{{ introTitle }}</h1>
      <p class="accredited-intro__subtitle">{{ introSubtitle }}</p>
    </header>

    <div v-if="status === 'loading'" class="accredited-intro__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="4" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />

    <div v-else class="accredited-intro__layout">
      <div class="accredited-intro__main">
        <DemoDataNotice scope="section" :modules="DEMO_MODULES" />
        <Card v-if="introDescription">
          <template #header>
            <h2 class="card__title-text">{{ t('accredited.intro.howItWorksTitle') }}</h2>
          </template>
          <p class="accredited-intro__lead">{{ introDescription }}</p>
        </Card>
        <Card v-if="whyRecommended">
          <template #header>
            <h2 class="card__title-text">{{ whyRecommended.title }}</h2>
          </template>
          <p class="accredited-intro__lead">{{ whyRecommended.body }}</p>
        </Card>
        <Card v-if="benefits.length">
          <template #header>
            <h2 class="card__title-text">{{ t('accredited.intro.benefitsTitle') }}</h2>
          </template>
          <ul class="accredited-intro__benefits">
            <li v-for="benefit in benefits" :key="benefit.id" class="accredited-intro__benefit">
              <span class="accredited-intro__benefit-icon" aria-hidden="true"
                ><AppIcon :name="benefit.icon || 'check'"
              /></span>
              <span class="accredited-intro__benefit-body">
                <span class="accredited-intro__benefit-title">{{ localized(benefit.title) }}</span>
                <span class="accredited-intro__benefit-desc">{{
                  localized(benefit.description)
                }}</span>
              </span>
            </li>
          </ul>
        </Card>
        <Card v-if="paymentCopy">
          <template #header>
            <h2 class="card__title-text">{{ paymentCopy.title }}</h2>
          </template>
          <p class="accredited-intro__lead">{{ paymentCopy.body }}</p>
        </Card>
        <div class="accredited-intro__note" role="note">
          <AppIcon name="shield-info" />
          <p>{{ t('accredited.intro.evidenceNote') }}</p>
        </div>
      </div>

      <aside class="accredited-intro__aside">
        <AccreditedPriceCard
          :title="feesCopy?.title || t('accredited.intro.feesTitle')"
          :description="feesCopy?.body || t('accredited.intro.feesDescription')"
          :price="price"
          :status="priceStatus"
        >
          <template v-if="action" #action>
            <BaseButton variant="primary" :icon="action.icon" @click="router.push(action.to)">
              {{ t(`accredited.intro.actions.${action.key}`) }}
            </BaseButton>
          </template>
        </AccreditedPriceCard>
        <AccreditedCurrentCard v-if="current" :current="current" />
      </aside>
    </div>
  </section>
</template>
