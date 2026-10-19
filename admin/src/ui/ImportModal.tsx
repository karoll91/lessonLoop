import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { api } from '../api/client'
import type { ImportReport } from '../api/types'
import { errorMessage, Modal, useToast } from './components'
import { DownloadIcon, UploadIcon } from './icons'

/**
 * Two-step import (W5/W6): a dry run shows what will be created, skipped and
 * rejected, then the same input is sent again to save it.
 */
export function ImportModal({
  title,
  endpoint,
  template,
  hint,
  placeholder,
  onClose,
  onImported,
}: {
  title: string
  endpoint: string
  template: 'students' | 'questions'
  hint: string
  placeholder: string
  onClose: () => void
  onImported: (report: ImportReport) => void
}) {
  const [mode, setMode] = useState<'file' | 'text'>('file')
  const [file, setFile] = useState<File | null>(null)
  const [text, setText] = useState('')
  const [preview, setPreview] = useState<ImportReport | null>(null)
  const toast = useToast()

  const body = (dryRun: boolean): FormData => {
    const data = new FormData()
    if (mode === 'file' && file !== null) {
      data.append('file', file)
    } else {
      data.append('text', text)
    }
    data.append('dry_run', dryRun ? '1' : '0')
    return data
  }

  const check = useMutation({
    mutationFn: () => api.post<ImportReport>(endpoint, body(true)),
    onSuccess: setPreview,
  })

  const commit = useMutation({
    mutationFn: () => api.post<ImportReport>(endpoint, body(false)),
    onSuccess: (report) => {
      toast(`${report.created_count} ta yozuv qo'shildi`)
      onImported(report)
      onClose()
    },
  })

  const ready = mode === 'file' ? file !== null : text.trim() !== ''
  const resetPreview = (): void => {
    setPreview(null)
    check.reset()
  }

  return (
    <Modal title={title} onClose={onClose} wide>
      {preview === null ? (
        <div className="col" style={{ gap: 14 }}>
          <div className="seg" role="group" aria-label="Manba">
            <button type="button" aria-pressed={mode === 'file'} onClick={() => setMode('file')}>
              Fayl (Excel/CSV)
            </button>
            <button type="button" aria-pressed={mode === 'text'} onClick={() => setMode('text')}>
              Matn
            </button>
          </div>
          {mode === 'file' ? (
            <label className="dropzone">
              <UploadIcon />
              <span style={{ fontWeight: 700 }}>{file ? file.name : 'Faylni tanlang'}</span>
              <span className="small">.xlsx, .xls, .csv — 5 MB gacha</span>
              <input type="file" accept=".xlsx,.xls,.csv,.txt" className="visually-hidden" onChange={(event) => setFile(event.target.files?.[0] ?? null)} />
            </label>
          ) : (
            <textarea className="textarea" rows={8} aria-label="Import matni" placeholder={placeholder} value={text} onChange={(event) => setText(event.target.value)} />
          )}
          <span className="small">{hint}</span>
          {check.isError && <div className="banner error">{errorMessage(check.error)}</div>}
          <div className="modal-actions" style={{ justifyContent: 'space-between' }}>
            <button type="button" className="btn ghost" onClick={() => void api.download(`/import-templates/${template}`, `lessonloop-${template}-shablon.xlsx`).catch((error) => toast(errorMessage(error), 'error'))}>
              <DownloadIcon />
              Shablon
            </button>
            <div className="row">
              <button type="button" className="btn" onClick={onClose}>
                Bekor
              </button>
              <button type="button" className="btn primary" disabled={!ready || check.isPending} onClick={() => check.mutate()}>
                {check.isPending && <span className="spinner" />}
                Tekshirish
              </button>
            </div>
          </div>
        </div>
      ) : (
        <div className="col" style={{ gap: 14 }}>
          <div className="row wrap">
            <span className="chip pass">{preview.created_count} ta qo'shiladi</span>
            <span className="chip">{preview.duplicate_count} ta takror</span>
            <span className={`chip ${preview.error_count > 0 ? 'danger' : ''}`}>{preview.error_count} ta xato</span>
          </div>
          <div className="table-wrap" style={{ maxHeight: 320, overflow: 'auto', border: '1px solid var(--line)', borderRadius: 10 }}>
            <table className="tb">
              <thead>
                <tr>
                  <th style={{ width: 60 }}>Qator</th>
                  <th>Qiymat</th>
                  <th>Natija</th>
                </tr>
              </thead>
              <tbody>
                {preview.errors.map((error) => (
                  <tr key={`e${error.row}`}>
                    <td className="num">{error.row}</td>
                    <td>{error.value || '—'}</td>
                    <td style={{ color: 'var(--danger)' }}>{error.message}</td>
                  </tr>
                ))}
                {preview.duplicates.map((duplicate) => (
                  <tr key={`d${duplicate.row}`}>
                    <td className="num">{duplicate.row}</td>
                    <td>{duplicate.value}</td>
                    <td className="muted">Allaqachon bor — o'tkaziladi</td>
                  </tr>
                ))}
                {preview.created.map((item) => (
                  <tr key={`c${String(item.row)}`}>
                    <td className="num">{String(item.row)}</td>
                    <td>{String(item.full_name ?? item.text ?? '')}</td>
                    <td style={{ color: 'var(--pass)' }}>Qo'shiladi</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {commit.isError && <div className="banner error">{errorMessage(commit.error)}</div>}
          <div className="modal-actions">
            <button type="button" className="btn" onClick={resetPreview}>
              Orqaga
            </button>
            <button type="button" className="btn primary" disabled={preview.created_count === 0 || commit.isPending} onClick={() => commit.mutate()}>
              {commit.isPending && <span className="spinner" />}
              {preview.created_count} tasini import qilish
            </button>
          </div>
        </div>
      )}
    </Modal>
  )
}
