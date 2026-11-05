<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Accordion from '@/components/ui/Accordion.vue'
import FeeSummary from '@/components/payments/FeeSummary.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'

const props = defineProps({
  product: { type: Object, required: true },
  pricing: { type: Object, default: null },
  localized: { type: Function, required: true },
  eligible: { type: Boolean, default: false },
})

const { t, n } = useI18n()

const marketing = computed(() => props.product?.marketing ?? {})
const currency = computed(() => props.pricing?.currency ?? 'SAR')

const lineItems = computed(() => {
  if (!props.pricing) return []
  const pct = Math.round((props.pricing.vatRate ?? 0) * 100)
  return [
    {
      label: t('bag.intro.price.beforeTax'),
      value: t('bag.intro.price.amount', {
        amount: n(props.pricing.priceBeforeTax, 'default'),
        currency: currency.value,
      }),
    },
    {
      label: t('bag.intro.price.vat', { pct: n(pct, 'id') }),
      value: t('bag.intro.price.amount', {
        amount: n(props.pricing.taxAmount, 'default'),
        currency: currency.value,
      }),
    },
  ]
})

const faqItems = computed(() =>
  (marketing.value.faqs ?? []).map((faq, index) => ({
    id: `bag-faq-${index}`,
    title: props.localized(faq.q),
    content: props.localized(faq.a),
  })),
)
</script>

<template>
  <div class="bag-intro">
    <header v-reveal class="bag-intro__hero">
      <p class="bag-intro__eyebrow">{{ t('bag.intro.eyebrow') }}</p>
      <h1 class="bag-intro__title">{{ localized(marketing.title) }}</h1>
      <p class="bag-intro__tagline">{{ localized(marketing.tagline) }}</p>
    </header>

    <div class="bag-intro__grid">
      <Card v-reveal class="bag-intro__block">
        <h2 class="bag-intro__block-title">{{ t('bag.intro.what.title') }}</h2>
        <p class="bag-intro__block-body">{{ localized(marketing.whatIsIt) }}</p>
      </Card>
      <Card v-reveal class="bag-intro__block">
        <h2 class="bag-intro__block-title">{{ t('bag.intro.why.title') }}</h2>
        <p class="bag-intro__block-body">{{ localized(marketing.whyNeed) }}</p>
      </Card>
    </div>

    <section v-reveal class="bag-intro__how" aria-labelledby="bag-intro-how">
      <h2 id="bag-intro-how" class="bag-intro__section-title">{{ t('bag.intro.how.title') }}</h2>
      <ol class="bag-intro__steps">
        <li
          v-for="(step, index) in marketing.howItWorks ?? []"
          :key="index"
          class="bag-intro__step"
        >
          <span class="bag-intro__step-num">{{ n(index + 1, 'id') }}</span>
          <span class="bag-intro__step-label">{{ localized(step) }}</span>
        </li>
      </ol>
    </section>

    <section v-reveal class="bag-intro__outputs" aria-labelledby="bag-intro-outputs">
      <h2 id="bag-intro-outputs" class="bag-intro__section-title">
        {{ t('bag.intro.outputs.title') }}
      </h2>
      <ul class="bag-intro__output-list">
        <li v-for="(out, index) in marketing.outputs ?? []" :key="index" class="bag-intro__output">
          <i class="fa-solid fa-circle-check bag-intro__output-icon" aria-hidden="true"></i>
          <span>{{ localized(out) }}</span>
        </li>
      </ul>
    </section>

    <section v-reveal class="bag-intro__pricing" aria-labelledby="bag-intro-pricing">
      <h2 id="bag-intro-pricing" class="bag-intro__section-title">
        {{ t('bag.intro.price.title') }}
      </h2>
      <FeeSummary
        v-if="pricing"
        :amount="pricing.total"
        :currency="currency"
        purpose="bag"
        :title="t('bag.intro.price.cardTitle')"
        :line-items="lineItems"
      >
        <template #action>
          <div class="bag-intro__cta">
            <p class="bag-intro__eligibility" :class="eligible ? 'is-eligible' : 'is-blocked'">
              <i
                :class="eligible ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-info'"
                aria-hidden="true"
              ></i>
              <span>{{ localized(marketing.eligibilityNote) }}</span>
            </p>

            <router-link
              v-if="eligible"
              class="bag-intro__pay-link"
              :to="{ name: ROUTE_NAMES.BAG_PAY }"
            >
              <BaseButton variant="primary" icon="fa-solid fa-credit-card" tabindex="-1">
                {{ t('bag.intro.cta.subscribe') }}
              </BaseButton>
            </router-link>

            <router-link
              v-else
              class="bag-intro__return-link"
              :to="{ name: ROUTE_NAMES.ASSESSMENT_ACCREDITED }"
            >
              <BaseButton variant="secondary" icon="fa-solid fa-clipboard-check" tabindex="-1">
                {{ t('bag.intro.cta.completeAccredited') }}
              </BaseButton>
            </router-link>
          </div>
        </template>
      </FeeSummary>
    </section>

    <section v-if="faqItems.length" v-reveal class="bag-intro__faq" aria-labelledby="bag-intro-faq">
      <h2 id="bag-intro-faq" class="bag-intro__section-title">{{ t('bag.intro.faq.title') }}</h2>
      <Accordion :items="faqItems" />
    </section>
  </div>
</template>
