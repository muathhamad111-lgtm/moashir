const PUBLIC_FILE_PATH = '/api/v1/content/files'

export const fixtureImageAssets = Object.freeze({
  '/generated/hero.jpg': '6f1d6c0e-4c1b-4d7a-9a51-1c2a3b4c5d01',
  '/generated/excellence-model.jpg': '6f1d6c0e-4c1b-4d7a-9a51-1c2a3b4c5d02',
  '/generated/fast-excellence-kit.jpg': '6f1d6c0e-4c1b-4d7a-9a51-1c2a3b4c5d03',
  '/generated/leadership.jpg': '6f1d6c0e-4c1b-4d7a-9a51-1c2a3b4c5d04',
  '/generated/kpi-dashboard.jpg': '6f1d6c0e-4c1b-4d7a-9a51-1c2a3b4c5d05',
  '/generated/analytics-lenses.jpg': '6f1d6c0e-4c1b-4d7a-9a51-1c2a3b4c5d06',
})

export function publicFileUrl(fileId) {
  return `${PUBLIC_FILE_PATH}/${fileId}`
}

export function fixtureImage(assetPath) {
  const fileId = fixtureImageAssets[assetPath]
  return fileId ? { fileId, url: publicFileUrl(fileId) } : null
}
