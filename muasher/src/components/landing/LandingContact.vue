<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePublicHome } from '@/composables/useHomeContent'
import { useLandingText } from '@/composables/landing/useLandingText'
import { SOCIAL_PLATFORMS } from '@/constants/socialPlatforms'
import AppIcon from '@/components/ui/AppIcon.vue'
import HomeContactForm from '@/components/public/HomeContactForm.vue'

defineProps({
  section: { type: Object, required: true },
})

const { t } = useI18n()
const { text, metaText } = useLandingText()
const { siteSettings } = usePublicHome({ auto: false })

const contact = computed(() => siteSettings.value?.contact ?? {})
const phoneHref = computed(() => `tel:${String(contact.value.phone ?? '').replace(/\s+/g, '')}`)
const social = computed(() =>
  (siteSettings.value?.social ?? [])
    .filter((link) => SOCIAL_PLATFORMS[link.platform] && link.url)
    .map((link) => ({ ...link, ...SOCIAL_PLATFORMS[link.platform] })),
)
</script>

<template>
  <section class="lp-sec lp-contact" aria-labelledby="lp-contact-title">
    <div class="lp-wrap lp-contact__grid">
      <div v-reveal class="lp-reveal">
        <span v-if="metaText(section, 'eyebrow')" class="lp-kicker">{{
          metaText(section, 'eyebrow')
        }}</span>
        <h2 id="lp-contact-title" class="lp-head__title">{{ text(section.title) }}</h2>
        <p class="lp-contact__lede">{{ text(section.description) }}</p>
        <ul class="lp-contact__list">
          <li v-if="contact.email">
            <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#lp-i-mail" /></svg>
            <div>
              <b>{{ metaText(section, 'emailLabel') }}</b>
              <a :href="`mailto:${contact.email}`" dir="ltr">{{ contact.email }}</a>
            </div>
          </li>
          <li v-if="contact.phone">
            <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#lp-i-phone" /></svg>
            <div>
              <b>{{ metaText(section, 'phoneLabel') }}</b>
              <a :href="phoneHref" dir="ltr">{{ contact.phone }}</a>
            </div>
          </li>
          <li v-if="text(contact.address)">
            <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#lp-i-pin" /></svg>
            <div>
              <b>{{ metaText(section, 'addressLabel') }}</b>
              <span>{{ text(contact.address) }}</span>
            </div>
          </li>
        </ul>
        <div v-if="social.length" class="lp-contact__social">
          <span>{{ metaText(section, 'socialLabel') }}</span>
          <a
            v-for="link in social"
            :key="link.url"
            :class="`is-${link.platform}`"
            :href="link.url"
            target="_blank"
            rel="noopener noreferrer"
            :aria-label="t('landing.contact.socialLink', { platform: t(link.labelKey) })"
          >
            <AppIcon :name="link.icon" />
          </a>
        </div>
      </div>
      <div v-reveal class="lp-contact__form lp-reveal">
        <HomeContactForm />
      </div>
    </div>
  </section>
</template>
