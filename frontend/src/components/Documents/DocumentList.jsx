import React, { useEffect, useState } from 'react'
import api from '../../services/api'
import '../../styles/DocumentList.css'

const DocumentList = () => {
  const [documents, setDocuments] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState(null)
  const [selectedDoc, setSelectedDoc] = useState(null)

  useEffect(() => {
    loadDocuments()
  }, [])

  const loadDocuments = async () => {
    try {
      setIsLoading(true)
      const response = await api.get('/documents')

      if (response.data.success) {
        setDocuments(response.data.data.documents)
      }
    } catch (err) {
      setError('Erreur lors du chargement des documents')
      console.error(err)
    } finally {
      setIsLoading(false)
    }
  }

  const viewDocument = async (id) => {
    try {
      const response = await api.get(`/documents/${id}`)

      if (response.data.success) {
        setSelectedDoc(response.data.data)
      }
    } catch (err) {
      alert('Erreur lors du chargement du document')
    }
  }

  const deleteDocument = async (id) => {
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce document ?')) {
      return
    }

    try {
      await api.delete(`/documents/${id}`)
      loadDocuments()
      if (selectedDoc?.id === id) {
        setSelectedDoc(null)
      }
      alert('Document supprimé')
    } catch (err) {
      alert('Erreur lors de la suppression')
    }
  }

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('fr-FR', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    })
  }

  const formatFileSize = (bytes) => {
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
  }

  if (isLoading) {
    return <div className="loading">Chargement...</div>
  }

  return (
    <div className="document-list">
      <div className="list-header">
        <h1>Mes documents</h1>
        <button onClick={loadDocuments} className="btn btn-secondary">
          Actualiser
        </button>
      </div>

      {error && <div className="error-message">{error}</div>}

      {documents.length === 0 ? (
        <div className="empty-state">
          <p>Aucun document trouvé</p>
          <p>Commencez par capturer ou uploader un document</p>
        </div>
      ) : (
        <div className="documents-container">
          <div className="documents-grid">
            {documents.map((doc) => (
              <div key={doc.id} className="document-card">
                <div className="document-info">
                  <h3>{doc.original_name}</h3>
                  <p className="doc-type">{doc.document_type}</p>
                  <p className="doc-date">{formatDate(doc.created_at)}</p>
                  <p className="doc-size">{formatFileSize(doc.file_size)}</p>
                  <p className="doc-confidence">
                    Confiance: {(doc.ocr_confidence * 100).toFixed(0)}%
                  </p>
                </div>
                <div className="document-actions">
                  <button
                    onClick={() => viewDocument(doc.id)}
                    className="btn btn-sm btn-primary"
                  >
                    Voir
                  </button>
                  <button
                    onClick={() => deleteDocument(doc.id)}
                    className="btn btn-sm btn-danger"
                  >
                    Supprimer
                  </button>
                </div>
              </div>
            ))}
          </div>

          {selectedDoc && (
            <div className="document-detail-modal" onClick={() => setSelectedDoc(null)}>
              <div className="modal-content" onClick={(e) => e.stopPropagation()}>
                <div className="modal-header">
                  <h2>{selectedDoc.original_name}</h2>
                  <button onClick={() => setSelectedDoc(null)} className="close-btn">
                    ×
                  </button>
                </div>

                <div className="modal-body">
                  <div className="detail-info">
                    <p><strong>Type:</strong> {selectedDoc.document_type}</p>
                    <p><strong>Taille:</strong> {formatFileSize(selectedDoc.file_size)}</p>
                    <p><strong>Moteur OCR:</strong> {selectedDoc.ocr_engine}</p>
                    <p><strong>Confiance:</strong> {(selectedDoc.ocr_confidence * 100).toFixed(1)}%</p>
                    <p><strong>Langue:</strong> {selectedDoc.language}</p>
                    <p><strong>Date:</strong> {formatDate(selectedDoc.created_at)}</p>
                  </div>

                  {selectedDoc.ocr_text && (
                    <div className="extracted-text">
                      <h3>Texte extrait:</h3>
                      <pre>{selectedDoc.ocr_text}</pre>
                    </div>
                  )}

                  {selectedDoc.ocr_data?.data && (
                    <div className="passport-data">
                      <h3>Données structurées:</h3>
                      <pre>{JSON.stringify(selectedDoc.ocr_data.data, null, 2)}</pre>
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

export default DocumentList
