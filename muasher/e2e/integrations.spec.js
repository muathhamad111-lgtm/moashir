import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import config from '../playwright.config.js'
import { seedSignedIn } from './support/session'
import { chooseNamed, namedSelect, openOption } from './support/dropdown'

const SYSTEM_ADMIN = { id: 2, roles: ['system_admin'], name: { ar: 'منيرة بنت خالد العتيبي', en: 'Munira Al-Otaibi' } }
const CONTENT_OFFICER = { id: 40, roles: ['content_officer'], name: 'Content Officer' }
const FINANCE = { id: 31, roles: ['finance_officer'], name: 'Finance Officer' }

const FAKE = {
  livePublishable: 'pk_live_fake_example_moashir02',
  liveSecret: 'sk_live_fake_example_replacement_5432',
  revokedLive: 'sk_live_fake_example_revoked_000001',
  aiKey: 'openai_fake_example_project_key_0001',
  smtpPassword: 'fake example mailbox passphrase',
}

const REASON = 'Checkout rehearsal before the award cycle opens.'

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffix(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function seed(page, testInfo, user = SYSTEM_ADMIN) {
  return seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar' })
}

function setProduction(page) {
  return page.context().addCookies([{ name: 'moashir_mock_env', value: 'production', url: config.use.baseURL }])
}

const saveButton = (page) => page.locator('.settings-section__footer button[type="submit"]')
const keyPair = (page, index) => page.locator('.key-pair').nth(index)
const radio = (page, name, value) => page.locator(`label.radio:has(input[name="${name}"][value="${value}"])`)
const checkbox = (page, name) => page.locator(`label.checkbox:has([name="${name}"])`)
const toggle = (page, name) => page.locator(`.toggle-switch__track:has(input[name="${name}"])`)
const button = (scope, pattern) => scope.getByRole('button', { name: pattern })
const card = (page, index) => page.locator('.integration-card').nth(index)

function settingsWrite(page) {
  return page.waitForResponse((response) => response.request().method() === 'PUT' && response.url().includes('/api/v1/settings/'))
}

async function save(page) {
  const written = settingsWrite(page)
  await saveButton(page).click()
  expect((await written).status()).toBe(200)
}

async function storeLiveKeys(page, secret = FAKE.liveSecret) {
  await page.locator('input[name="live.publishableKey"]').fill(FAKE.livePublishable)
  await page.locator('input[name="live.secretKey"]').fill(secret)
  await save(page)
}

async function expectNoSecretAnywhere(page, secret) {
  await expect(page.locator('body')).not.toContainText(secret)
  const values = await page.locator('input').evaluateAll((inputs) => inputs.map((input) => input.value))
  expect(values).not.toContain(secret)
  expect(page.url()).not.toContain(secret)
  const storage = await page.evaluate(() => JSON.stringify({ ...localStorage, ...sessionStorage }))
  expect(storage).not.toContain(secret)
}

test('the overview shows every kind with its status, source and last test', async ({ page }, testInfo) => {
  await seed(page, testInfo)
  await page.goto('/admin/settings/integrations')
  const cards = page.locator('.overview-card')
  await expect(cards).toHaveCount(4)
  await expect(cards.nth(0)).toContainText(/Active|مفعّل/)
  await expect(cards.nth(2)).toContainText(/Source: environment \(bootstrap\)|المصدر: متغيرات البيئة/)
  await expect(cards.nth(3)).toContainText(/Not configured|غير مهيأ/)
  await expect(cards.nth(0).locator('.last-test')).toContainText(/منيرة بنت خالد العتيبي/)
  await page.screenshot({ path: `test-results/integrations-overview-${suffix(testInfo)}.png`, fullPage: true })
  await cards.nth(1).getByRole('link').click()
  await expect(page).toHaveURL(/\/admin\/settings\/sms$/)
})

test('payment keys are write-only, live needs a passing test and values can be copied', async ({ page, context }, testInfo) => {
  test.setTimeout(60000)
  await context.grantPermissions(['clipboard-read', 'clipboard-write'])
  await seed(page, testInfo)
  await page.goto('/admin/settings/payment')
  await expect(keyPair(page, 0).locator('.secret-input__status').first()).toContainText('••••')
  await expect(page.locator('input[name="mode"][value="live"]')).toBeDisabled()

  await storeLiveKeys(page)
  await expectNoSecretAnywhere(page, FAKE.liveSecret)
  await expect(keyPair(page, 1).locator('.secret-input__last4').first()).toContainText('5432')

  await button(keyPair(page, 1), /Test connection|اختبار الاتصال/).click()
  await expect(keyPair(page, 1).locator('.test-result')).toContainText(/Connection test passed|نجح اختبار الاتصال/)
  await expect(page.locator('input[name="mode"][value="live"]')).toBeEnabled()
  await radio(page, 'mode', 'live').click()
  await toggle(page, 'active').click()
  await save(page)
  await expect(keyPair(page, 1)).toContainText(/Selected environment|البيئة المختارة/)

  const copy = page.locator('.copy-value').first().getByRole('button')
  await copy.click()
  await expect(page.locator('.copy-value__status').first()).toContainText(/copied|تم نسخ/)
  expect(await page.evaluate(() => navigator.clipboard.readText())).toContain('/api/v1/webhooks/moyasar')
  await page.screenshot({ path: `test-results/integrations-payment-${suffix(testInfo)}.png`, fullPage: true })
})

test('the Apple Pay checklist uploads the association file and unlocks Apple Pay after confirmation', async ({ page }, testInfo) => {
  await seed(page, testInfo)
  await page.goto('/admin/settings/payment')
  const applePay = page.locator('label.checkbox:has([name="methods-applepay"])')
  await expect(page.locator('input[name="methods-applepay"]')).toBeDisabled()
  await page.locator('.association-file input[type="file"]').setInputFiles({
    name: 'apple-developer-merchantid-domain-association',
    mimeType: 'text/plain',
    buffer: Buffer.from('7B226D65726368616E744964656E746966696572223A22'),
  })
  await expect(page.locator('.association-file__state')).toContainText(/bytes|بايت/)
  await expect(page.locator('.provider-steps__item').nth(2)).toContainText(/Done|منجز/)
  await button(page, /Check file is reachable|التحقق من إتاحة الملف/).click()
  await expect(page.locator('.association-file')).toContainText(/File is reachable|الملف متاح/)
  await checkbox(page, 'confirmations.applePayDomain').click()
  await expect(page.locator('input[name="methods-applepay"]')).toBeEnabled()
  await applePay.click()
  await save(page)
  await expect(page.locator('input[name="methods-applepay"]')).toBeChecked()
})

test('a rejected key shows the failed checks, the translated error kind and the fourth test is rate limited', async ({ page }, testInfo) => {
  test.setTimeout(60000)
  await seed(page, testInfo)
  await page.goto('/admin/settings/payment')
  await storeLiveKeys(page, FAKE.revokedLive)
  const live = keyPair(page, 1)
  await button(live, /Test connection|اختبار الاتصال/).click()
  await expect(live.locator('.provider-feedback')).toContainText(/rejected the credentials|رفض المزوّد بيانات الاعتماد/)
  await expect(live.locator('.test-result--failed')).toContainText(/Secret key|المفتاح السري/)
  await expect(live.locator('.last-test')).toContainText(/Failed|فشل/)
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await button(live, /Test connection|اختبار الاتصال/).click()
    await expect(live.locator('.connection-test .btn--loading')).toHaveCount(0)
  }
  await expect(live.locator('.provider-feedback--rate_limited')).toContainText(/Try again in|أعد المحاولة بعد/)
})

test('production in test mode shows the banner and asks for a reason when leaving live', async ({ page }, testInfo) => {
  test.setTimeout(60000)
  await setProduction(page)
  await seed(page, testInfo)
  await page.goto('/admin/settings/payment')
  await expect(page.locator('.test-mode-banner')).toContainText(/no real charge|لا يوجد خصم فعلي/)
  await storeLiveKeys(page)
  await button(keyPair(page, 1), /Test connection|اختبار الاتصال/).click()
  await expect(keyPair(page, 1).locator('.test-result')).toBeVisible()
  await radio(page, 'mode', 'live').click()
  await save(page)
  await expect(page.locator('.test-mode-banner')).toHaveCount(0)

  await radio(page, 'mode', 'test').click()
  await saveButton(page).click()
  const dialog = page.locator('.modal__panel')
  await expect(dialog).toContainText(/Reason for this change|سبب هذا التغيير/)
  await dialog.locator('[name="reason"]').fill(REASON)
  await dialog.locator('button[type="submit"]').click()
  await expect(page.locator('.toast--success').last()).toBeVisible()
  await expect(page.locator('.test-mode-banner')).toBeVisible()
  await page.goto('/admin/settings/integrations')
  await expect(page.locator('.test-mode-banner')).toBeVisible()
})

test('sms providers, Saudi test numbers, test send and the Msegat template check', async ({ page }, testInfo) => {
  test.setTimeout(60000)
  await seed(page, testInfo)
  await page.goto('/admin/settings/sms')
  await button(page, /Load senders|تحميل أسماء المرسلين/).click()
  await expect(card(page, 1)).toContainText(/2 active senders loaded|حُمّل 2/)
  await chooseNamed(page, 'providers.taqnyat.senderName', 'MoashirInfo')
  await page.locator('.phone-list__input').fill('+201000000003')
  await button(page, /Add number|إضافة الرقم/).click()
  await expect(page.locator('.phone-list')).toContainText(/Saudi mobile number|رقم جوال سعوديًا/)
  await page.locator('.phone-list__input').fill('+966500000003')
  await button(page, /Add number|إضافة الرقم/).click()
  await expect(page.locator('.phone-list__item')).toHaveCount(3)
  await expect(page.locator('.sms-counter__summary').first()).toContainText(/SMS|رسالة/)
  await save(page)

  await page.locator('input[name="recipient"]').fill('+966500000009')
  await button(page, /^Send test SMS$|^إرسال رسالة تجريبية$/).click()
  await expect(page.locator('.provider-feedback--rejected')).toContainText(/allowed test numbers|أرقام الاختبار المسموح بها/)
  await page.locator('input[name="recipient"]').fill('+966500000003')
  await button(page, /^Send test SMS$|^إرسال رسالة تجريبية$/).click()
  await expect(page.locator('.provider-feedback--success')).toContainText(/Taqnyat|تقنيات/)

  await radio(page, 'activeProvider', 'msegat').click()
  await checkbox(page, 'providers.msegat.templateRestricted').click()
  await expect(page.locator('.sms-templates__check.is-pending')).toBeVisible()
  await button(page, /Load Msegat templates|تحميل قوالب مسجات/).click()
  await expect(page.locator('.msegat-templates__item')).toHaveCount(3)
  await expect(page.locator('.sms-counter__match.is-approved')).toHaveCount(2)
  await page.locator('textarea[name="sms-template-otp_code-ar"]').fill('رمزكم في منصة مؤشر هو {code}.')
  await expect(page.locator('.sms-counter__match.is-unapproved')).toHaveCount(1)
  await page.screenshot({ path: `test-results/integrations-sms-${suffix(testInfo)}.png`, fullPage: true })
  await saveButton(page).click()
  await expect(page.locator('.template-editor .field__error').first()).toContainText(/approved Msegat template|قالب معتمد للمرسل في مسجات/)
})

test('e-mail moves from the environment bootstrap to settings with the DNS checklist and a test e-mail', async ({ page }, testInfo) => {
  test.setTimeout(60000)
  await seed(page, testInfo)
  await page.goto('/admin/settings/email')
  await expect(page.locator('.environment-note')).toBeVisible()
  await expect(card(page, 0)).toContainText(/Source: environment|المصدر: متغيرات البيئة/)
  await button(page, /Test connection|اختبار الاتصال/).click()
  await expect(page.locator('.test-result')).toContainText(/Encrypted connection and sign-in|الاتصال المشفّر وتسجيل الدخول/)
  const dns = page.locator('.dns-checklist__item')
  await expect(dns.nth(0)).toContainText(/Found|موجود/)
  await expect(dns.nth(2)).toContainText(/Not found|غير موجود/)

  await radio(page, 'mode', 'test').click()
  await saveButton(page).click()
  await expect(page.locator('input[name="testAddress"]').locator('xpath=ancestor::div[contains(@class,"field")][1]')).toContainText(/Required in test mode|مطلوب في وضع الاختبار/)
  await expect(card(page, 0).locator('.secret-input')).toContainText(/Enter the value before you save|أدخل القيمة قبل الحفظ/)
  await page.locator('input[name="testAddress"]').fill('it-team@moashir.example.sa')
  await button(card(page, 0), /^Replace$|^استبدال$/).click()
  await page.locator('input[name="password"]').fill(FAKE.smtpPassword)
  await save(page)
  await expectNoSecretAnywhere(page, FAKE.smtpPassword)
  await expect(card(page, 0)).toContainText(/Source: settings|المصدر: الإعدادات/)
  await expect(page.locator('.environment-note')).toHaveCount(0)

  await page.locator('input[name="recipient"]').fill('it-team@moashir.example.sa')
  await button(page, /^Send test e-mail$|^إرسال بريد تجريبي$/).click()
  await expect(page.locator('.provider-feedback--success')).toContainText(/test mailbox|بريد الاختبار/)
  await page.screenshot({ path: `test-results/integrations-email-${suffix(testInfo)}.png`, fullPage: true })
})

test('an ai provider needs a key, a model and its own data transfer acknowledgement before it is activated', async ({ page }, testInfo) => {
  test.setTimeout(60000)
  await seed(page, testInfo)
  await page.goto('/admin/settings/ai')
  await expect(card(page, 0)).toContainText(/Not configured|غير مهيأ/)
  const openai = card(page, 1)
  await expect(button(openai, /Load models|تحميل النماذج/)).toBeDisabled()
  await page.locator('input[name="providers.openai.apiKey"]').fill(FAKE.aiKey)
  await save(page)
  await expectNoSecretAnywhere(page, FAKE.aiKey)

  await button(openai, /Load models|تحميل النماذج/).click()
  await namedSelect(page, 'providers.openai.model').click()
  await expect(page.locator('[role="listbox"] [role="option"]')).toHaveCount(3)
  await openOption(page, 'gpt-4.1-mini').click()
  await radio(page, 'activeProvider', 'openai').click()
  await saveButton(page).click()
  await expect(card(page, 0)).toContainText(/Tick the data transfer acknowledgement|حدّد الإقرار بنقل البيانات/)
  await checkbox(page, 'providers.openai.dataTransferAcknowledged').click()
  await save(page)
  await expect(openai.locator('.ai-acknowledgement')).toContainText(/Munira Al-Otaibi|منيرة بنت خالد العتيبي/)
  await expect(card(page, 0)).toContainText(/Active|مفعّل/)

  await button(openai, /Test answer|اختبار إجابة/).click()
  await expect(openai.locator('.ai-answer__result')).toContainText('gpt-4.1-mini')
  await page.screenshot({ path: `test-results/integrations-ai-${suffix(testInfo)}.png`, fullPage: true })
})

test('integration screens stay hidden from the content officer and the finance officer', async ({ page }, testInfo) => {
  await seed(page, testInfo, CONTENT_OFFICER)
  await page.goto('/admin/settings')
  await expect(page.locator('.settings-hub__card[href="/admin/settings/ai/knowledge-base"]')).toBeVisible()
  for (const path of ['/admin/settings/integrations', '/admin/settings/payment', '/admin/settings/sms', '/admin/settings/email', '/admin/settings/ai']) {
    await expect(page.locator(`.settings-hub__card[href="${path}"]`)).toHaveCount(0)
  }
  await page.goto('/admin/settings/ai')
  await expect(page).toHaveURL(/forbidden|403/)
  await page.goto('/admin/settings/ai/knowledge-base')
  await expect(page.locator('.settings-form__kb-list, .empty-state')).toBeVisible()

  await seed(page, testInfo, FINANCE)
  await page.goto('/admin/settings/integrations')
  await expect(page).toHaveURL(/forbidden|403/)
})
