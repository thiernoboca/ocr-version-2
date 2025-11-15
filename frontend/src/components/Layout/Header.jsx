import React from 'react'
import { Link, useLocation } from 'react-router-dom'
import '../../styles/Header.css'

const Header = ({ onLogout }) => {
  const location = useLocation()

  return (
    <header className="app-header">
      <div className="header-content">
        <div className="logo">
          <Link to="/dashboard">OCR System</Link>
        </div>

        <nav className="nav-menu">
          <Link
            to="/dashboard"
            className={location.pathname === '/dashboard' ? 'active' : ''}
          >
            Tableau de bord
          </Link>
          <Link
            to="/capture"
            className={location.pathname === '/capture' ? 'active' : ''}
          >
            Capturer
          </Link>
          <Link
            to="/upload"
            className={location.pathname === '/upload' ? 'active' : ''}
          >
            Upload
          </Link>
          <Link
            to="/documents"
            className={location.pathname === '/documents' ? 'active' : ''}
          >
            Documents
          </Link>
        </nav>

        <button onClick={onLogout} className="logout-btn">
          Déconnexion
        </button>
      </div>
    </header>
  )
}

export default Header
