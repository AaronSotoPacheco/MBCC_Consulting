// Reduce una foto del dispositivo (lado mayor 1600 px, JPEG) antes de enviarla:
// sube más rápido con datos móviles, cabe en el límite del servidor y la IA cuesta menos.
export async function reducirImagen(archivo, lado = 1600, calidad = 0.85) {
  if (!archivo?.type?.startsWith('image/')) throw new Error('El archivo debe ser una imagen (JPG, PNG o WebP).')
  let bmp
  try {
    bmp = await createImageBitmap(archivo, { imageOrientation: 'from-image' })
  } catch {
    throw new Error('No se pudo leer la imagen. Prueba con una foto JPG, PNG o WebP.')
  }
  const k = Math.min(1, lado / Math.max(bmp.width, bmp.height))
  const lienzo = document.createElement('canvas')
  lienzo.width = Math.max(1, Math.round(bmp.width * k))
  lienzo.height = Math.max(1, Math.round(bmp.height * k))
  lienzo.getContext('2d').drawImage(bmp, 0, 0, lienzo.width, lienzo.height)
  bmp.close?.()
  const blob = await new Promise((ok) => lienzo.toBlob(ok, 'image/jpeg', calidad))
  if (!blob) throw new Error('No se pudo preparar la imagen.')
  return blob
}
