# Système de Capture et OCR de Documents

Un système complet de capture et traitement de documents combinant **Dynamsoft JavaScript SDK** (frontend) avec **PassportEye** et **Tesseract** via PHP (backend).

## 🌟 Caractéristiques

- ✅ **Capture en direct** via webcam (Dynamsoft Document Normalizer)
- ✅ **Upload de fichiers** (images et PDF)
- ✅ **OCR multi-moteur** : PassportEye (passeports) + Tesseract (documents généraux)
- ✅ **Traitement d'images** : détection de contours, redressement automatique, amélioration
- ✅ **API RESTful** sécurisée
- ✅ **Conformité RGPD** : chiffrement, suppression automatique, logs d'audit
- ✅ **Support multilingue** pour l'OCR

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      FRONTEND (JavaScript)                   │
│  - Dynamsoft Document Normalizer (capture en direct)        │
│  - Interface d'upload                                        │
│  - Prévisualisation et recadrage                            │
└─────────────────────────────────────────────────────────────┘
                            ↓ HTTPS
┌─────────────────────────────────────────────────────────────┐
│                      BACKEND (PHP)                           │
│  - API REST (routes sécurisées)                             │
│  - Traitement d'images (GD/Imagick)                         │
│  - OCR: PassportEye + Tesseract                             │
│  - Gestion base de données (MySQL)                          │
│  - Chiffrement et sécurité                                  │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                   STOCKAGE & BASE DE DONNÉES                 │
│  - MySQL (métadonnées, résultats OCR)                       │
│  - Stockage local chiffré (documents temporaires)           │
└─────────────────────────────────────────────────────────────┘
```

## 📋 Prérequis

### Backend (PHP)
- PHP 8.1 ou supérieur
- Extensions PHP : `gd`, `mbstring`, `zip`, `xml`, `fileinfo`, `pdo_mysql`
- Tesseract OCR installé : `sudo apt-get install tesseract-ocr tesseract-ocr-fra tesseract-ocr-eng`
- PassportEye : `pip install PassportEye` (Python requis)
- Composer
- MySQL 8.0 ou supérieur

### Frontend (JavaScript)
- Node.js 18+ et npm/yarn
- Navigateur moderne avec support WebRTC (pour capture webcam)

## 🚀 Installation

### 1. Backend PHP

```bash
cd backend

# Installer les dépendances PHP
composer install

# Copier et configurer l'environnement
cp .env.example .env
# Éditer .env avec vos paramètres (base de données, clés API Dynamsoft)

# Créer la base de données
mysql -u root -p < ../database/schema.sql

# Définir les permissions
chmod -R 755 storage
chmod -R 777 storage/app/documents
chmod -R 777 storage/logs
```

### 2. Frontend JavaScript

```bash
cd frontend

# Installer les dépendances
npm install

# Configurer l'environnement
cp .env.example .env
# Ajouter votre clé de licence Dynamsoft dans .env

# Lancer en développement
npm run dev

# Build pour production
npm run build
```

## ⚙️ Configuration

### Clé de Licence Dynamsoft

1. Obtenez une clé de licence gratuite : https://www.dynamsoft.com/customer/license/trialLicense
2. Ajoutez-la dans :
   - `frontend/.env` : `VITE_DYNAMSOFT_LICENSE=VOTRE_CLE`
   - `backend/.env` : `DYNAMSOFT_LICENSE=VOTRE_CLE`

### Configuration Tesseract

Les langues OCR se configurent dans `backend/.env` :
```env
TESSERACT_LANGUAGES=fra+eng  # Français + Anglais
```

Installer d'autres langues :
```bash
sudo apt-get install tesseract-ocr-deu  # Allemand
sudo apt-get install tesseract-ocr-spa  # Espagnol
```

### Configuration RGPD

Dans `backend/.env` :
```env
# Suppression automatique des documents après X jours
DOCUMENT_RETENTION_DAYS=30

# Chiffrement des données sensibles
ENCRYPTION_KEY=votre_cle_32_caracteres_minimum

# Logs d'audit
AUDIT_LOG_ENABLED=true
```

## 📖 Utilisation

### API Endpoints

#### Upload et OCR de document
```bash
POST /api/documents/upload
Content-Type: multipart/form-data

{
  "file": <image/pdf>,
  "type": "passport|document|invoice",  # Type de document
  "language": "fra+eng"  # Langues OCR
}

Response:
{
  "success": true,
  "document_id": "abc123",
  "ocr_result": {
    "text": "...",
    "confidence": 0.95,
    "fields": { ... }
  }
}
```

#### Récupérer un document
```bash
GET /api/documents/{id}

Response:
{
  "id": "abc123",
  "filename": "passport.jpg",
  "type": "passport",
  "ocr_text": "...",
  "created_at": "2025-11-15T10:30:00Z"
}
```

#### Supprimer un document (RGPD)
```bash
DELETE /api/documents/{id}

Response:
{
  "success": true,
  "message": "Document supprimé définitivement"
}
```

### Interface Frontend

1. **Capture en direct** : Utilisez votre webcam pour capturer un document
2. **Upload** : Glissez-déposez ou sélectionnez un fichier
3. **Traitement** : Le système détecte automatiquement les contours et redresse le document
4. **OCR** : Extraction automatique du texte avec sélection du moteur approprié
5. **Résultats** : Visualisation du texte extrait avec niveau de confiance

## 🔒 Sécurité et Conformité RGPD

### Mesures de sécurité

- ✅ **Chiffrement** : AES-256 pour les données sensibles
- ✅ **Validation** : Tous les uploads sont validés (type, taille, contenu)
- ✅ **Sanitization** : Nettoyage des entrées utilisateur
- ✅ **HTTPS obligatoire** en production
- ✅ **Rate limiting** sur les API endpoints
- ✅ **Authentification** : JWT tokens
- ✅ **CORS** configuré de manière restrictive

### Conformité RGPD

- ✅ **Droit à l'oubli** : Suppression complète des données
- ✅ **Minimisation** : Seules les données nécessaires sont conservées
- ✅ **Rétention limitée** : Suppression automatique après N jours
- ✅ **Logs d'audit** : Traçabilité des accès et traitements
- ✅ **Consentement** : Formulaires de consentement explicite
- ✅ **Portabilité** : Export des données au format JSON

## 🧪 Tests

### Backend
```bash
cd backend
./vendor/bin/phpunit tests/
```

### Frontend
```bash
cd frontend
npm run test
```

## 📊 Performance

- **Capture en direct** : < 100ms pour détection de document
- **Upload** : Support jusqu'à 10MB par fichier
- **OCR Tesseract** : ~2-5s pour une page A4
- **OCR PassportEye** : ~1s pour un passeport

## 🛠️ Dépannage

### Tesseract ne fonctionne pas
```bash
# Vérifier l'installation
tesseract --version

# Vérifier les langues installées
tesseract --list-langs
```

### Problèmes de permissions
```bash
sudo chown -R www-data:www-data backend/storage
sudo chmod -R 775 backend/storage
```

### Erreurs Dynamsoft
- Vérifiez que votre clé de licence est valide
- Assurez-vous que votre domaine est autorisé
- Consultez la console du navigateur pour les erreurs JavaScript

## 📝 Licence

Ce projet est sous licence MIT.

## 🤝 Support

Pour toute question ou problème :
- Ouvrez une issue sur GitHub
- Consultez la documentation Dynamsoft : https://www.dynamsoft.com/document-normalizer/docs/
- Documentation Tesseract : https://tesseract-ocr.github.io/

## 🔄 Versions

- **v2.0** - Architecture complète avec Dynamsoft + PassportEye + Tesseract
- **v1.0** - Version initiale (obsolète)
