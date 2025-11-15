# Guide d'Installation - Système OCR de Documents

Guide complet pour installer et configurer le système OCR avec Dynamsoft JavaScript SDK, PassportEye et Tesseract.

## Sommaire

1. [Prérequis](#prérequis)
2. [Installation avec Docker](#installation-avec-docker)
3. [Installation manuelle](#installation-manuelle)
4. [Configuration](#configuration)
5. [Vérification](#vérification)
6. [Dépannage](#dépannage)

---

## Prérequis

### Système
- **OS**: Linux (Ubuntu 20.04+), macOS, ou Windows avec WSL2
- **RAM**: 2GB minimum, 4GB recommandé
- **Espace disque**: 5GB minimum

### Logiciels requis

#### Pour Docker (recommandé)
- Docker 20.10+
- Docker Compose 2.0+

#### Pour installation manuelle
- **PHP**: 8.1 ou supérieur
- **Node.js**: 18.0 ou supérieur
- **MySQL**: 8.0 ou supérieur
- **Python**: 3.8 ou supérieur
- **Tesseract OCR**: 4.0 ou supérieur

---

## Installation avec Docker

### 1. Cloner le projet

```bash
git clone https://github.com/votre-repo/ocr-version-2.git
cd ocr-version-2
```

### 2. Configuration

```bash
# Backend
cd backend
cp .env.example .env
# Éditer .env et configurer :
# - DYNAMSOFT_LICENSE (votre clé Dynamsoft)
# - DB_PASSWORD (mot de passe MySQL)
# - ENCRYPTION_KEY (clé de 32 caractères minimum)
# - JWT_SECRET (clé secrète pour JWT)

# Frontend
cd ../frontend
cp .env.example .env
# Éditer .env et ajouter :
# - VITE_DYNAMSOFT_LICENSE (même clé que backend)
```

### 3. Obtenir une licence Dynamsoft

1. Visitez https://www.dynamsoft.com/customer/license/trialLicense
2. Inscrivez-vous pour obtenir une clé d'essai gratuite (30 jours)
3. Copiez la clé dans les fichiers `.env` (backend et frontend)

### 4. Lancer avec Docker Compose

```bash
cd ..  # Revenir à la racine
docker-compose up -d
```

Cela va démarrer :
- MySQL (port 3306)
- Backend PHP (port 8080)
- Frontend React (port 3000)
- PhpMyAdmin (port 8081)

### 5. Initialiser la base de données

La base de données sera automatiquement créée au premier démarrage grâce au script `database/schema.sql`.

### 6. Accéder à l'application

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8080
- **PhpMyAdmin**: http://localhost:8081

Compte de test par défaut :
- Email: `test@example.com`
- Mot de passe: `password`

---

## Installation manuelle

### 1. Installation des dépendances système

#### Ubuntu/Debian

```bash
# Mise à jour
sudo apt update && sudo apt upgrade -y

# PHP et extensions
sudo apt install -y php8.1 php8.1-cli php8.1-fpm php8.1-mysql php8.1-gd \
    php8.1-mbstring php8.1-zip php8.1-xml php8.1-curl

# MySQL
sudo apt install -y mysql-server

# Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Python et pip
sudo apt install -y python3 python3-pip

# Tesseract OCR avec langues
sudo apt install -y tesseract-ocr tesseract-ocr-fra tesseract-ocr-eng

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

#### macOS

```bash
# Homebrew
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# PHP
brew install php@8.1

# MySQL
brew install mysql
brew services start mysql

# Node.js
brew install node@18

# Python
brew install python@3.11

# Tesseract
brew install tesseract tesseract-lang
```

### 2. Installation de PassportEye

```bash
pip3 install PassportEye
```

### 3. Configuration MySQL

```bash
# Se connecter à MySQL
sudo mysql

# Créer la base de données et l'utilisateur
mysql> CREATE DATABASE ocr_documents CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
mysql> CREATE USER 'ocr_user'@'localhost' IDENTIFIED BY 'votre_mot_de_passe';
mysql> GRANT ALL PRIVILEGES ON ocr_documents.* TO 'ocr_user'@'localhost';
mysql> FLUSH PRIVILEGES;
mysql> exit;

# Importer le schéma
mysql -u ocr_user -p ocr_documents < database/schema.sql
```

### 4. Backend PHP

```bash
cd backend

# Installer les dépendances
composer install

# Configuration
cp .env.example .env
nano .env  # Éditer la configuration

# Créer les répertoires nécessaires
mkdir -p storage/logs storage/app/documents
chmod -R 775 storage
```

Configuration minimale dans `.env` :
```env
DYNAMSOFT_LICENSE=VOTRE_CLE_DYNAMSOFT
DB_HOST=localhost
DB_DATABASE=ocr_documents
DB_USERNAME=ocr_user
DB_PASSWORD=votre_mot_de_passe
ENCRYPTION_KEY=votre_cle_32_caracteres_minimum_xyz
JWT_SECRET=votre_secret_jwt_unique
```

### 5. Frontend React

```bash
cd ../frontend

# Installer les dépendances
npm install

# Configuration
cp .env.example .env
nano .env

# Configuration minimale
VITE_DYNAMSOFT_LICENSE=VOTRE_CLE_DYNAMSOFT
VITE_API_URL=http://localhost:8080/api
```

### 6. Lancement

#### Backend avec PHP Built-in Server (développement)

```bash
cd backend/public
php -S localhost:8080
```

#### Frontend avec Vite

```bash
cd frontend
npm run dev
```

L'application sera accessible sur http://localhost:3000

---

## Configuration de production

### 1. Apache/Nginx

#### Apache

```apache
<VirtualHost *:80>
    ServerName ocr.example.com
    DocumentRoot /var/www/ocr-version-2/backend/public

    <Directory /var/www/ocr-version-2/backend/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # SSL (recommandé)
    # SSLEngine on
    # SSLCertificateFile /path/to/cert.pem
    # SSLCertificateKeyFile /path/to/key.pem

    ErrorLog ${APACHE_LOG_DIR}/ocr-error.log
    CustomLog ${APACHE_LOG_DIR}/ocr-access.log combined
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name ocr.example.com;
    root /var/www/ocr-version-2/backend/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # SSL (recommandé)
    # listen 443 ssl;
    # ssl_certificate /path/to/cert.pem;
    # ssl_certificate_key /path/to/key.pem;
}
```

### 2. Build Frontend pour production

```bash
cd frontend
npm run build

# Les fichiers seront dans frontend/dist/
# À servir avec Apache/Nginx ou CDN
```

### 3. Cron Job pour nettoyage automatique (RGPD)

```bash
crontab -e

# Ajouter cette ligne pour exécuter tous les jours à 2h du matin
0 2 * * * /usr/bin/php /var/www/ocr-version-2/backend/scripts/cleanup_expired_documents.php >> /var/log/ocr-cleanup.log 2>&1
```

### 4. Sécurité en production

Dans `backend/.env`, modifier :
```env
APP_ENV=production
APP_DEBUG=false
```

Configurer HTTPS obligatoire et désactiver les CORS ouverts.

---

## Vérification

### Test de santé du système

```bash
curl http://localhost:8080/api/health
```

Réponse attendue :
```json
{
  "success": true,
  "status": "healthy",
  "checks": {
    "api": true,
    "database": true,
    "tesseract": true,
    "python": true,
    "storage": true
  }
}
```

### Test OCR

1. Accédez à http://localhost:3000
2. Connectez-vous avec `test@example.com` / `password`
3. Essayez d'uploader une image avec du texte
4. Vérifiez que l'OCR fonctionne

---

## Dépannage

### Tesseract n'est pas trouvé

```bash
# Vérifier l'installation
tesseract --version

# Vérifier le chemin
which tesseract

# Mettre à jour dans .env
TESSERACT_PATH=/usr/bin/tesseract  # ou le chemin retourné par 'which'
```

### PassportEye ne fonctionne pas

```bash
# Réinstaller
pip3 uninstall PassportEye
pip3 install PassportEye

# Vérifier Python
python3 --version
which python3

# Mettre à jour dans .env
PYTHON_PATH=/usr/bin/python3
```

### Erreur de permissions

```bash
cd backend
sudo chown -R www-data:www-data storage
sudo chmod -R 775 storage
```

### Erreur de connexion base de données

Vérifier dans `.env` :
- `DB_HOST` (localhost ou 127.0.0.1)
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

Tester la connexion :
```bash
mysql -h localhost -u ocr_user -p ocr_documents
```

### Erreur licence Dynamsoft

1. Vérifiez que la clé est valide sur https://www.dynamsoft.com/customer/license/fullLicense
2. Vérifiez que le domaine est autorisé
3. Regardez la console du navigateur pour plus de détails

### Port déjà utilisé

```bash
# Changer le port dans docker-compose.yml ou dans la commande de lancement
# Backend : modifier ports: "8081:80" au lieu de "8080:80"
# Frontend : npm run dev -- --port 3001
```

---

## Support

Pour toute question :
- Documentation Dynamsoft : https://www.dynamsoft.com/document-normalizer/docs/
- Documentation Tesseract : https://tesseract-ocr.github.io/
- Issues GitHub : https://github.com/votre-repo/ocr-version-2/issues

---

## Licence

MIT License - Voir le fichier LICENSE pour plus de détails.
