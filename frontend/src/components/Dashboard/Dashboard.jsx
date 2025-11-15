import React from 'react'
import { Link } from 'react-router-dom'
import '../../styles/Dashboard.css'

const Dashboard = () => {
  return (
    <div className="dashboard">
      <div className="dashboard-header">
        <h1>Tableau de bord</h1>
        <p>Choisissez une action</p>
      </div>

      <div className="action-cards">
        <Link to="/capture" className="action-card">
          <div className="card-icon">
            <svg viewBox="0 0 24 24" width="48" height="48">
              <path fill="currentColor" d="M12 15c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3z"/>
              <path fill="currentColor" d="M9 2L7.17 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2h-3.17L15 2H9zm3 15c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/>
            </svg>
          </div>
          <h2>Capture en direct</h2>
          <p>Utilisez votre webcam pour capturer un document</p>
        </Link>

        <Link to="/upload" className="action-card">
          <div className="card-icon">
            <svg viewBox="0 0 24 24" width="48" height="48">
              <path fill="currentColor" d="M9 16h6v-6h4l-7-7-7 7h4v6zm-4 2h14v2H5v-2z"/>
            </svg>
          </div>
          <h2>Upload de fichier</h2>
          <p>Uploadez un document depuis votre ordinateur</p>
        </Link>

        <Link to="/documents" className="action-card">
          <div className="card-icon">
            <svg viewBox="0 0 24 24" width="48" height="48">
              <path fill="currentColor" d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 5h-3v5.5c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5V7H9V5h9v2zm-7.5 9c-1.83 0-3.38.95-4.25 2.38A2.99 2.99 0 0 0 4 21v1h12v-1c0-1.38-.56-2.63-1.47-3.53a5.97 5.97 0 0 0-3.03-1.47z"/>
              <path fill="currentColor" d="M2 6v14c0 1.1.9 2 2 2h14v-2H4V6H2z"/>
            </svg>
          </div>
          <h2>Mes documents</h2>
          <p>Consultez vos documents analysés</p>
        </Link>
      </div>

      <div className="info-section">
        <h2>À propos du système</h2>
        <div className="info-grid">
          <div className="info-item">
            <h3>Capture intelligente</h3>
            <p>Détection automatique des contours et redressement des documents</p>
          </div>
          <div className="info-item">
            <h3>OCR multi-moteur</h3>
            <p>PassportEye pour les passeports, Tesseract pour les documents généraux</p>
          </div>
          <div className="info-item">
            <h3>RGPD compliant</h3>
            <p>Chiffrement des données et suppression automatique après 30 jours</p>
          </div>
          <div className="info-item">
            <h3>Multilingue</h3>
            <p>Support de multiples langues: Français, Anglais, Allemand, Espagnol...</p>
          </div>
        </div>
      </div>
    </div>
  )
}

export default Dashboard
