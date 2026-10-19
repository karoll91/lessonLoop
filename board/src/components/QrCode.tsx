import QRCode from 'qrcode'
import { useEffect, useState } from 'react'

/** QR code drawn as SVG; the phone app scans it to pair with this board. */
export function QrCode({ value, label }: { value: string; label: string }) {
  const [svg, setSvg] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false

    QRCode.toString(value, { type: 'svg', margin: 0, errorCorrectionLevel: 'M', color: { dark: '#101317', light: '#ffffff' } })
      .then((markup) => {
        if (!cancelled) {
          setSvg(markup)
        }
      })
      .catch(() => setSvg(null))

    return () => {
      cancelled = true
    }
  }, [value])

  return (
    <div className="qr" role="img" aria-label={label}>
      {svg !== null && <div style={{ width: '100%', height: '100%' }} dangerouslySetInnerHTML={{ __html: svg }} />}
    </div>
  )
}
