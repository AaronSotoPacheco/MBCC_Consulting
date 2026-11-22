import { useEffect, useRef, useState } from 'react'

// Lee un código QR con la cámara (BarcodeDetector del navegador si existe; si no, jsQR) y llama a onCodigo(texto) una vez.
// La cámara solo funciona en HTTPS o en localhost; en HTTP desde otro equipo el navegador la bloquea.
export default function EscanerQR({ onCodigo }) {
  const videoRef = useRef(null)
  const alDetectar = useRef(onCodigo)
  const [error, setError] = useState('')
  const [listo, setListo] = useState(false)
  useEffect(() => { alDetectar.current = onCodigo })

  useEffect(() => {
    let vivo = true
    let flujo = null
    let cuadro = 0
    let ocupado = false
    let ultimo = 0
    const video = videoRef.current
    const lienzo = document.createElement('canvas')

    const iniciar = async () => {
      if (!navigator.mediaDevices?.getUserMedia) {
        setError('Este navegador no permite usar la cámara aquí. En el celular abre la app con HTTPS o usa un lector de gafetes.')
        return
      }
      try {
        flujo = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false })
      } catch (e) {
        if (vivo) setError(e?.name === 'NotAllowedError' ? 'Permite el acceso a la cámara para escanear tu gafete.' : 'No se pudo abrir la cámara.')
        return
      }
      if (!vivo) { flujo.getTracks().forEach((t) => t.stop()); return }
      video.srcObject = flujo
      await video.play().catch(() => {})
      setListo(true)

      const detector = 'BarcodeDetector' in window ? new window.BarcodeDetector({ formats: ['qr_code'] }) : null
      const jsQR = detector ? null : (await import('jsqr')).default

      const leer = async () => {
        if (detector) return (await detector.detect(video))[0]?.rawValue || null
        const k = Math.min(1, 640 / Math.max(video.videoWidth, video.videoHeight))
        lienzo.width = Math.round(video.videoWidth * k)
        lienzo.height = Math.round(video.videoHeight * k)
        const ctx = lienzo.getContext('2d', { willReadFrequently: true })
        ctx.drawImage(video, 0, 0, lienzo.width, lienzo.height)
        const img = ctx.getImageData(0, 0, lienzo.width, lienzo.height)
        return jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' })?.data || null
      }

      const ciclo = async (t) => {
        if (!vivo) return
        cuadro = requestAnimationFrame(ciclo)
        if (ocupado || t - ultimo < 150 || video.readyState < 2 || !video.videoWidth) return
        ultimo = t
        ocupado = true
        try {
          const texto = await leer()
          if (texto && vivo) { vivo = false; cancelAnimationFrame(cuadro); alDetectar.current(texto) }
        } catch { /* fotograma ilegible: se intenta con el siguiente */ } finally { ocupado = false }
      }
      cuadro = requestAnimationFrame(ciclo)
    }
    iniciar()

    return () => {
      vivo = false
      cancelAnimationFrame(cuadro)
      flujo?.getTracks().forEach((t) => t.stop())
    }
  }, [])

  return (
    <div className="escaner">
      {error ? <p className="error" role="alert">{error}</p> : (
        <>
          <video ref={videoRef} playsInline muted aria-label="Vista de la cámara" />
          <div className="escaner-marco" aria-hidden="true" />
          <p className="muted small escaner-ayuda" aria-live="polite">{listo ? 'Apunta la cámara al QR de tu gafete' : 'Abriendo la cámara…'}</p>
        </>
      )}
    </div>
  )
}
