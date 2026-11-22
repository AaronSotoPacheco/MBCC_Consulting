import QRCode from 'qrcode'

// Imagen (data URL PNG) del código QR de un texto. Corrección de errores M: se lee bien aunque el gafete se ensucie un poco.
export const qrImagen = (texto, ancho = 320) => QRCode.toDataURL(texto, { errorCorrectionLevel: 'M', margin: 1, width: ancho })
