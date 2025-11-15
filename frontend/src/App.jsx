import React, { useState } from 'react'
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom'
import Login from './components/Auth/Login'
import Register from './components/Auth/Register'
import Dashboard from './components/Dashboard/Dashboard'
import DocumentCapture from './components/Capture/DocumentCapture'
import DocumentUpload from './components/Upload/DocumentUpload'
import DocumentList from './components/Documents/DocumentList'
import Header from './components/Layout/Header'
import './styles/App.css'

function App() {
  const [isAuthenticated, setIsAuthenticated] = useState(!!localStorage.getItem('token'))

  const handleLogin = (token) => {
    localStorage.setItem('token', token)
    setIsAuthenticated(true)
  }

  const handleLogout = () => {
    localStorage.removeItem('token')
    setIsAuthenticated(false)
  }

  return (
    <Router>
      <div className="app">
        {isAuthenticated && <Header onLogout={handleLogout} />}

        <Routes>
          {/* Routes publiques */}
          <Route
            path="/login"
            element={
              isAuthenticated ? (
                <Navigate to="/dashboard" replace />
              ) : (
                <Login onLogin={handleLogin} />
              )
            }
          />
          <Route
            path="/register"
            element={
              isAuthenticated ? (
                <Navigate to="/dashboard" replace />
              ) : (
                <Register onLogin={handleLogin} />
              )
            }
          />

          {/* Routes protégées */}
          <Route
            path="/dashboard"
            element={
              isAuthenticated ? (
                <Dashboard />
              ) : (
                <Navigate to="/login" replace />
              )
            }
          />
          <Route
            path="/capture"
            element={
              isAuthenticated ? (
                <DocumentCapture />
              ) : (
                <Navigate to="/login" replace />
              )
            }
          />
          <Route
            path="/upload"
            element={
              isAuthenticated ? (
                <DocumentUpload />
              ) : (
                <Navigate to="/login" replace />
              )
            }
          />
          <Route
            path="/documents"
            element={
              isAuthenticated ? (
                <DocumentList />
              ) : (
                <Navigate to="/login" replace />
              )
            }
          />

          {/* Redirection par défaut */}
          <Route
            path="/"
            element={
              <Navigate to={isAuthenticated ? "/dashboard" : "/login"} replace />
            }
          />
        </Routes>
      </div>
    </Router>
  )
}

export default App
