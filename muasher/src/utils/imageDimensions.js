export function imageDimensionsOf(file) {
  return new Promise((resolve) => {
    const url = URL.createObjectURL(file)
    const image = new Image()
    image.onload = () => {
      resolve({ width: image.naturalWidth, height: image.naturalHeight })
      URL.revokeObjectURL(url)
    }
    image.onerror = () => {
      resolve(null)
      URL.revokeObjectURL(url)
    }
    image.src = url
  })
}
