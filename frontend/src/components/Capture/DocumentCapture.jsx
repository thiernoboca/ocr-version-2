import React, { useEffect, useRef, useState } from 'react'
import { CoreModule } from 'dynamsoft-core'
import { LicenseManager } from 'dynamsoft-license'
import { CameraEnhancer, CameraView } from 'dynamsoft-camera-enhancer'
import { CaptureVisionRouter } from 'dynamsoft-capture-vision-router'
import { DocumentNormalizer } from 'dynamsoft-document-normalizer'
import api from '../../services/api'
import '../../styles/DocumentCapture.css'

const DocumentCapture = () => {
  const cameraViewContainerRef = useRef(null)
  const resultsContainerRef = useRef(null)
  const [isInitialized, setIsInitialized] = useState(false)
  const [isCapturing, setIsCapturing] = useState(false)
  const [capturedImage, setCapturedImage] = useState(null)
  const [ocrResult, setOcrResult] = useState(null)
  const [isProcessing, setIsProcessing] = useState(false)
  const [error, setError] = useState(null)
  const [documentType, setDocumentType] = useState('document')
  const [language, setLanguage] = useState('fra+eng')

  const cameraEnhancerRef = useRef(null)
  const routerRef = useRef(null)

  useEffect(() => {
    const init = async () => {
      try {
        // Initialiser la licence Dynamsoft
        await LicenseManager.initLicense(import.meta.env.VITE_DYNAMSOFT_LICENSE)

        // Charger les ressources Dynamsoft
        await CoreModule.loadWasm(['DDN'])

        // Créer la vue de la caméra
        const cameraView = await CameraView.createInstance()
        if (cameraViewContainerRef.current) {
          cameraViewContainerRef.current.append(cameraView.getUIElement())
        }

        // Créer le Camera Enhancer
        const cameraEnhancer = await CameraEnhancer.createInstance(cameraView)
        cameraEnhancerRef.current = cameraEnhancer

        // Créer le router de capture de vision
        const router = await CaptureVisionRouter.createInstance()
        routerRef.current = router

        // Connecter le router au camera enhancer
        router.setInput(cameraEnhancer)

        // Configurer la détection de documents
        const settings = await router.getSimplifiedSettings('DetectDocumentBoundaries_Default')
        settings.roi.points = [
          { x: 10, y: 10 },
          { x: 90, y: 10 },
          { x: 90, y: 90 },
          { x: 10, y: 90 }
        ]
        await router.updateSettings('DetectDocumentBoundaries_Default', settings)

        // Gérer les résultats de détection
        router.addResultReceiver({
          onCapturedResultReceived: (result) => {
            const items = result.items
            if (items.length > 0) {
              // Afficher les contours détectés
              displayDetectedBoundaries(items)
            }
          }
        })

        setIsInitialized(true)
        console.log('Dynamsoft initialisé avec succès')

      } catch (err) {
        console.error('Erreur d\'initialisation Dynamsoft:', err)
        setError(`Erreur d'initialisation: ${err.message}`)
      }
    }

    init()

    // Nettoyage
    return () => {
      if (cameraEnhancerRef.current) {
        cameraEnhancerRef.current.dispose()
      }
      if (routerRef.current) {
        routerRef.current.dispose()
      }
    }
  }, [])

  const displayDetectedBoundaries = (items) => {
    // Afficher visuellement les contours détectés sur la caméra
    // Cette fonction dessine un overlay sur la caméra
    if (resultsContainerRef.current && items[0]) {
      const corners = items[0].location.points
      const canvas = document.createElement('canvas')
      const ctx = canvas.getContext('2d')

      ctx.strokeStyle = '#00ff00'
      ctx.lineWidth = 3
      ctx.beginPath()
      ctx.moveTo(corners[0].x, corners[0].y)
      for (let i = 1; i < corners.length; i++) {
        ctx.lineTo(corners[i].x, corners[i].y)
      }
      ctx.closePath()
      ctx.stroke()
    }
  }

  const startCapture = async () => {
    try {
      setIsCapturing(true)
      setError(null)

      // Ouvrir la caméra
      await cameraEnhancerRef.current.open()

      // Démarrer la capture
      await routerRef.current.startCapturing('DetectDocumentBoundaries_Default')

    } catch (err) {
      console.error('Erreur démarrage capture:', err)
      setError(`Erreur: ${err.message}`)
      setIsCapturing(false)
    }
  }

  const stopCapture = async () => {
    try {
      await routerRef.current.stopCapturing()
      await cameraEnhancerRef.current.close()
      setIsCapturing(false)
    } catch (err) {
      console.error('Erreur arrêt capture:', err)
    }
  }

  const captureDocument = async () => {
    try {
      setIsProcessing(true)
      setError(null)

      // Capturer l'image actuelle
      const result = await routerRef.current.capture(null, 'DetectAndNormalizeDocument')

      if (result.items.length === 0) {
        setError('Aucun document détecté. Veuillez positionner le document dans le cadre.')
        setIsProcessing(false)
        return
      }

      // Récupérer l'image normalisée
      const normalizedImage = result.items[0].toCanvas()

      // Convertir en blob
      normalizedImage.toBlob(async (blob) => {
        // Afficher l'aperçu
        const imageUrl = URL.createObjectURL(blob)
        setCapturedImage(imageUrl)

        // Envoyer au backend pour OCR
        await processOCR(blob)
      }, 'image/jpeg', 0.95)

    } catch (err) {
      console.error('Erreur capture document:', err)
      setError(`Erreur de capture: ${err.message}`)
    } finally {
      setIsProcessing(false)
    }
  }

  const processOCR = async (imageBlob) => {
    try {
      setIsProcessing(true)

      // Créer le FormData
      const formData = new FormData()
      formData.append('file', imageBlob, 'captured_document.jpg')
      formData.append('type', documentType)
      formData.append('language', language)

      // Envoyer au backend
      const response = await api.post('/documents/upload', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })

      if (response.data.success) {
        setOcrResult(response.data.data.ocr_result)
      } else {
        setError('Erreur lors du traitement OCR')
      }

    } catch (err) {
      console.error('Erreur OCR:', err)
      setError(`Erreur OCR: ${err.response?.data?.error || err.message}`)
    } finally {
      setIsProcessing(false)
    }
  }

  const resetCapture = () => {
    setCapturedImage(null)
    setOcrResult(null)
    setError(null)
  }

  return (
    <div className="document-capture">
      <div className="capture-header">
        <h1>Capture de Document en Direct</h1>
        <p>Utilisez votre webcam pour capturer des documents</p>
      </div>

      {error && (
        <div className="error-message">
          <strong>Erreur:</strong> {error}
        </div>
      )}

      <div className="capture-container">
        {/* Vue de la caméra */}
        <div className="camera-section">
          <div
            ref={cameraViewContainerRef}
            className="camera-view"
            style={{ display: capturedImage ? 'none' : 'block' }}
          ></div>
          <div ref={resultsContainerRef} className="detection-overlay"></div>

          {/* Image capturée */}
          {capturedImage && (
            <div className="captured-preview">
              <img src={capturedImage} alt="Document capturé" />
            </div>
          )}
        </div>

        {/* Contrôles */}
        <div className="controls-section">
          <div className="capture-options">
            <div className="form-group">
              <label htmlFor="documentType">Type de document:</label>
              <select
                id="documentType"
                value={documentType}
                onChange={(e) => setDocumentType(e.target.value)}
                disabled={isProcessing}
              >
                <option value="document">Document général</option>
                <option value="passport">Passeport</option>
                <option value="id_card">Carte d'identité</option>
                <option value="invoice">Facture</option>
                <option value="receipt">Reçu</option>
              </select>
            </div>

            <div className="form-group">
              <label htmlFor="language">Langue OCR:</label>
              <select
                id="language"
                value={language}
                onChange={(e) => setLanguage(e.target.value)}
                disabled={isProcessing}
              >
                <option value="fra+eng">Français + Anglais</option>
                <option value="fra">Français</option>
                <option value="eng">Anglais</option>
                <option value="deu">Allemand</option>
                <option value="spa">Espagnol</option>
              </select>
            </div>
          </div>

          <div className="capture-buttons">
            {!isCapturing && !capturedImage && (
              <button
                onClick={startCapture}
                disabled={!isInitialized || isProcessing}
                className="btn btn-primary"
              >
                Démarrer la caméra
              </button>
            )}

            {isCapturing && !capturedImage && (
              <>
                <button
                  onClick={captureDocument}
                  disabled={isProcessing}
                  className="btn btn-success"
                >
                  {isProcessing ? 'Capture en cours...' : 'Capturer le document'}
                </button>
                <button
                  onClick={stopCapture}
                  disabled={isProcessing}
                  className="btn btn-secondary"
                >
                  Arrêter
                </button>
              </>
            )}

            {capturedImage && (
              <button
                onClick={resetCapture}
                className="btn btn-secondary"
              >
                Nouvelle capture
              </button>
            )}
          </div>
        </div>
      </div>

      {/* Résultats OCR */}
      {ocrResult && (
        <div className="ocr-results">
          <h2>Résultats OCR</h2>
          <div className="result-info">
            <p><strong>Moteur:</strong> {ocrResult.engine}</p>
            <p><strong>Confiance:</strong> {(ocrResult.confidence * 100).toFixed(1)}%</p>
            <p><strong>Temps de traitement:</strong> {ocrResult.processing_time}s</p>
          </div>

          {ocrResult.text && (
            <div className="extracted-text">
              <h3>Texte extrait:</h3>
              <pre>{ocrResult.text}</pre>
            </div>
          )}

          {ocrResult.data && ocrResult.engine === 'passporteye' && (
            <div className="passport-data">
              <h3>Données du passeport:</h3>
              <table>
                <tbody>
                  <tr>
                    <td>Nom:</td>
                    <td>{ocrResult.data.surname}</td>
                  </tr>
                  <tr>
                    <td>Prénoms:</td>
                    <td>{ocrResult.data.names}</td>
                  </tr>
                  <tr>
                    <td>Numéro:</td>
                    <td>{ocrResult.data.number}</td>
                  </tr>
                  <tr>
                    <td>Pays:</td>
                    <td>{ocrResult.data.country}</td>
                  </tr>
                  <tr>
                    <td>Date de naissance:</td>
                    <td>{ocrResult.data.date_of_birth}</td>
                  </tr>
                  <tr>
                    <td>Date d'expiration:</td>
                    <td>{ocrResult.data.expiration_date}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

export default DocumentCapture
