export async function settleFonts(page) {
  await page.evaluate(() => document.fonts.ready)
}
