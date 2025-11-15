import React, { useState, useRef } from 'react'
import api from '../../services/api'
import '../../styles/DocumentUpload.css'

const DocumentUpload = () => {
  const [selectedFile, setSelectedFile] = useState(null)
  const [preview, setPreview] = useState(null)
  const [documentType, setDocumentType] = useState('document')
  const [language, setLanguage] = useState('fra+eng')
  const [isUploading, setIsUploading] = useState(false)
  const [ocrResult, setOcrResult] = useState(null)
  const [error, setError] = useState(null)
  const [dragActive, setDragActive] = useState(false)
  const fileInputRef = useRef(null)

  const handleFileSelect = (file) => {
    if (!file) return

    // Valider le type de fichier
    const allowedTypes = ['image/jpeg', 'image/png', 'image/tiff', 'application/pdf']
    if (!allowedTypes.includes(file.type)) {
      setError('Type de fichier non autorisé. Formats acceptés: JPEG, PNG, TIFF, PDF')
      return
    }

    // Valider la taille (10MB max)
    const maxSize = parseInt(import.meta.env.VITE_MAX_FILE_SIZE) || 10485760
    if (file.size > maxSize) {
      setError(`Fichier trop volumineux (max: ${(maxSize / 1024 / 1024).toFixed(0)}MB)`)
      return
    }

    setSelectedFile(file)
    setError(null)

    // Créer un aperçu
    if (file.type.startsWith('image/')) {
      const reader = new FileReader()
      reader.onloadend = () => {
        setPreview(reader.result)
      }
      reader.readAsDataURL(file)
    } else {
      setPreview(null)
    }
  }

  const handleDrag = (e) => {
    e.preventDefault()
    e.stopPropagation()
    if (e.type === 'dragenter' || e.type === 'dragover') {
      setDragActive(true)
    } else if (e.type === 'dragleave') {
      setDragActive(false)
    }
  }

  const handleDrop = (e) => {
    e.preventDefault()
    e.stopPropagation()
    setDragActive(false)

    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      handleFileSelect(e.dataTransfer.files[0])
    }
  }

  const handleUpload = async () => {
    if (!selectedFile) {
      setError('Veuillez sélectionner un fichier')
      return
    }

    try {
      setIsUploading(true)
      setError(null)
      setOcrResult(null)

      const formData = new FormData()
      formData.append('file', selectedFile)
      formData.append('type', documentType)
      formData.append('language', language)

      const response = await api.post('/documents/upload', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        },
        onUploadProgress: (progressEvent) => {
          const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total)
          console.log(`Upload: ${percentCompleted}%`)
        }
      })

      if (response.data.success) {
        setOcrResult(response.data.data.ocr_result)
      } else {
        setError('Erreur lors du traitement du document')
      }

    } catch (err) {
      console.error('Erreur upload:', err)
      setError(err.response?.data?.error || 'Erreur lors de l\'upload')
    } finally {
      setIsUploading(false)
    }
  }

  const resetForm = () => {
    setSelectedFile(null)
    setPreview(null)
    setOcrResult(null)
    setError(null)
    if (fileInputRef.current) {
      fileInputRef.current.value = ''
    }
  }

  return (
    <div className="document-upload">
      <div className="upload-header">
        <h1>Upload de Document</h1>
        <p>Sélectionnez ou glissez-déposez un document pour l'analyser</p>
      </div>

      {error && (
        <div className="error-message">
          <strong>Erreur:</strong> {error}
        </div>
      )}

      {!ocrResult && (
        <div className="upload-section">
          {/* Zone de drop */}
          <div
            className={`drop-zone ${dragActive ? 'active' : ''}`}
            onDragEnter={handleDrag}
            onDragLeave={handleDrag}
            onDragOver={handleDrag}
            onDrop={handleDrop}
            onClick={() => fileInputRef.current?.click()}
          >
            {preview ? (
              <div className="preview-container">
                <img src={preview} alt="Aperçu" />
                <p className="file-name">{selectedFile?.name}</p>
              </div>
            ) : (
              <div className="drop-zone-content">
                <svg className="upload-icon" viewBox="0 0 24 24" width="64" height="64">
                  <path fill="currentColor" d="M9 16h6v-6h4l-7-7-7 7h4v6zm-4 2h14v2H5v-2z" />
                </svg>
                <p className="drop-text">
                  {selectedFile ? selectedFile.name : 'Glissez-déposez un fichier ici'}
                </p>
                <p className="drop-subtext">ou cliquez pour sélectionner</p>
                <p className="file-types">JPEG, PNG, TIFF, PDF (max 10MB)</p>
              </div>
            )}
          </div>

          <input
            ref={fileInputRef}
            type="file"
            accept="image/jpeg,image/png,image/tiff,application/pdf"
            onChange={(e) => handleFileSelect(e.target.files[0])}
            style={{ display: 'none' }}
          />

          {/* Options */}
          <div className="upload-options">
            <div className="form-group">
              <label htmlFor="documentType">Type de document:</label>
              <select
                id="documentType"
                value={documentType}
                onChange={(e) => setDocumentType(e.target.value)}
                disabled={isUploading}
              >
                <option value="document">Document général</option>
                <option value="passport">Passeport</option>
                <option value="id_card">Carte d'identité</option>
                <option value="invoice">Facture</option>
                <option value="receipt">Reçu</option>
                <option value="other">Autre</option>
              </select>
            </div>

            <div className="form-group">
              <label htmlFor="language">Langue OCR:</label>
              <select
                id="language"
                value={language}
                onChange={(e) => setLanguage(e.target.value)}
                disabled={isUploading}
              >
                <option value="fra+eng">Français + Anglais</option>
                <option value="fra">Français</option>
                <option value="eng">Anglais</option>
                <option value="deu">Allemand</option>
                <option value="spa">Espagnol</option>
                <option value="ita">Italien</option>
              </select>
            </div>
          </div>

          {/* Boutons */}
          <div className="upload-buttons">
            <button
              onClick={handleUpload}
              disabled={!selectedFile || isUploading}
              className="btn btn-primary"
            >
              {isUploading ? 'Traitement en cours...' : 'Analyser le document'}
            </button>
            {selectedFile && (
              <button
                onClick={resetForm}
                disabled={isUploading}
                className="btn btn-secondary"
              >
                Annuler
              </button>
            )}
          </div>
        </div>
      )}

      {/* Résultats OCR */}
      {ocrResult && (
        <div className="ocr-results">
          <h2>Résultats de l'analyse</h2>

          <div className="result-info">
            <div className="info-card">
              <span className="label">Moteur OCR:</span>
              <span className="value">{ocrResult.engine}</span>
            </div>
            <div className="info-card">
              <span className="label">Confiance:</span>
              <span className="value confidence">
                {(ocrResult.confidence * 100).toFixed(1)}%
              </span>
            </div>
            <div className="info-card">
              <span className="label">Temps:</span>
              <span className="value">{ocrResult.processing_time}s</span>
            </div>
          </div>

          {ocrResult.text && (
            <div className="extracted-text">
              <h3>Texte extrait:</h3>
              <div className="text-container">
                <pre>{ocrResult.text}</pre>
                <button
                  className="btn btn-copy"
                  onClick={() => {
                    navigator.clipboard.writeText(ocrResult.text)
                    alert('Texte copié dans le presse-papiers')
                  }}
                >
                  Copier
                </button>
              </div>
            </div>
          )}

          {ocrResult.data && ocrResult.engine === 'passporteye' && (
            <div className="passport-data">
              <h3>Informations du passeport:</h3>
              <div className="data-grid">
                <div className="data-item">
                  <span className="label">Nom:</span>
                  <span className="value">{ocrResult.data.surname}</span>
                </div>
                <div className="data-item">
                  <span className="label">Prénoms:</span>
                  <span className="value">{ocrResult.data.names}</span>
                </div>
                <div className="data-item">
                  <span className="label">Numéro:</span>
                  <span className="value">{ocrResult.data.number}</span>
                </div>
                <div className="data-item">
                  <span className="label">Nationalité:</span>
                  <span className="value">{ocrResult.data.nationality}</span>
                </div>
                <div className="data-item">
                  <span className="label">Date de naissance:</span>
                  <span className="value">{ocrResult.data.date_of_birth}</span>
                </div>
                <div className="data-item">
                  <span className="label">Date d'expiration:</span>
                  <span className="value">{ocrResult.data.expiration_date}</span>
                </div>
                <div className="data-item">
                  <span className="label">Sexe:</span>
                  <span className="value">{ocrResult.data.sex}</span>
                </div>
              </div>
            </div>
          )}

          <div className="result-actions">
            <button onClick={resetForm} className="btn btn-primary">
              Analyser un autre document
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

export default DocumentUpload
