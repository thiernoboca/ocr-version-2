# Guide de Déploiement en Production

Guide pour déployer le système OCR de documents en production avec haute disponibilité, sécurité et conformité RGPD.

## Table des matières

1. [Architecture de production](#architecture-de-production)
2. [Déploiement sur serveur Linux](#déploiement-sur-serveur-linux)
3. [Déploiement Docker](#déploiement-docker)
4. [Configuration SSL/TLS](#configuration-ssltls)
5. [Optimisation des performances](#optimisation-des-performances)
6. [Monitoring et logs](#monitoring-et-logs)
7. [Sauvegardes](#sauvegardes)
8. [Conformité RGPD](#conformité-rgpd)

---

## Architecture de production

### Architecture recommandée

```
┌─────────────────────────────────────────────────────────┐
│                    Load Balancer / CDN                   │
│                     (Cloudflare, Nginx)                  │
└─────────────────────────────────────────────────────────┘
                            │
        ┌───────────────────┼───────────────────┐
        ▼                   ▼                   ▼
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│   Frontend   │    │   Frontend   │    │   Frontend   │
│   (Nginx)    │    │   (Nginx)    │    │   (Nginx)    │
└──────────────┘    └──────────────┘    └──────────────┘
                            │
                            ▼
                ┌───────────────────────┐
                │   API Load Balancer   │
                └───────────────────────┘
                            │
        ┌───────────────────┼───────────────────┐
        ▼                   ▼                   ▼
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  Backend PHP │    │  Backend PHP │    │  Backend PHP │
│  (PHP-FPM)   │    │  (PHP-FPM)   │    │  (PHP-FPM)   │
└──────────────┘    └──────────────┘    └──────────────┘
                            │
                            ▼
                ┌───────────────────────┐
                │  MySQL Primary/Replica│
                │  (avec réplication)   │
                └───────────────────────┘
                            │
                            ▼
                ┌───────────────────────┐
                │  Storage (S3/local)   │
                └───────────────────────┘
```

---

## Déploiement sur serveur Linux

### 1. Préparation du serveur

```bash
# Ubuntu 22.04 LTS recommandé
sudo apt update && sudo apt upgrade -y

# Installer les dépendances
sudo apt install -y nginx php8.1-fpm php8.1-mysql php8.1-gd php8.1-mbstring \
    php8.1-zip php8.1-xml php8.1-curl mysql-server \
    certbot python3-certbot-nginx tesseract-ocr tesseract-ocr-fra \
    tesseract-ocr-eng python3 python3-pip git unzip

# Installer PassportEye
sudo pip3 install PassportEye

# Installer Node.js (pour build frontend)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
```

### 2. Configuration MySQL

```bash
# Sécuriser MySQL
sudo mysql_secure_installation

# Créer la base de données
sudo mysql -e "CREATE DATABASE ocr_documents CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'ocr_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';"
sudo mysql -e "GRANT ALL PRIVILEGES ON ocr_documents.* TO 'ocr_user'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

# Optimiser MySQL pour production
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

Configuration MySQL optimisée :
```ini
[mysqld]
# Performance
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
max_connections = 200

# Sécurité
bind-address = 127.0.0.1
local-infile = 0
```

```bash
sudo systemctl restart mysql
```

### 3. Déploiement Backend

```bash
# Créer le répertoire
sudo mkdir -p /var/www/ocr-system
sudo chown -R www-data:www-data /var/www/ocr-system

# Cloner ou copier le projet
cd /var/www/ocr-system
sudo -u www-data git clone https://github.com/votre-repo/ocr-version-2.git .

# Backend
cd backend
sudo -u www-data composer install --no-dev --optimize-autoloader

# Configuration
sudo -u www-data cp .env.example .env
sudo nano .env
```

Configuration `.env` de production :
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ocr.votre-domaine.com

DYNAMSOFT_LICENSE=VOTRE_CLE_PRODUCTION

DB_HOST=localhost
DB_DATABASE=ocr_documents
DB_USERNAME=ocr_user
DB_PASSWORD=STRONG_PASSWORD_HERE

# Générer avec: openssl rand -base64 32
ENCRYPTION_KEY=VOTRE_CLE_CHIFFREMENT_32_CARACTERES
JWT_SECRET=VOTRE_SECRET_JWT_UNIQUE

TESSERACT_PATH=/usr/bin/tesseract
PYTHON_PATH=/usr/bin/python3

# RGPD
DOCUMENT_RETENTION_DAYS=30
AUTO_DELETE_ENABLED=true
AUDIT_LOG_ENABLED=true

# CORS (domaines autorisés uniquement)
CORS_ALLOWED_ORIGINS=https://ocr.votre-domaine.com
```

```bash
# Permissions
sudo chown -R www-data:www-data /var/www/ocr-system
sudo chmod -R 755 /var/www/ocr-system
sudo chmod -R 775 /var/www/ocr-system/backend/storage

# Importer le schéma
mysql -u ocr_user -p ocr_documents < /var/www/ocr-system/database/schema.sql
```

### 4. Déploiement Frontend

```bash
cd /var/www/ocr-system/frontend

# Configuration
cp .env.example .env
nano .env
```

Configuration `.env` frontend :
```env
VITE_API_URL=https://ocr.votre-domaine.com/api
VITE_DYNAMSOFT_LICENSE=VOTRE_CLE_PRODUCTION
```

```bash
# Build
npm ci
npm run build

# Les fichiers sont dans dist/
```

### 5. Configuration Nginx

```bash
sudo nano /etc/nginx/sites-available/ocr-system
```

Configuration Nginx complète :
```nginx
# Frontend
server {
    listen 80;
    listen [::]:80;
    server_name ocr.votre-domaine.com;

    # Redirection HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ocr.votre-domaine.com;

    # SSL (géré par Certbot)
    ssl_certificate /etc/letsencrypt/live/ocr.votre-domaine.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ocr.votre-domaine.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Headers de sécurité
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Frontend statique
    root /var/www/ocr-system/frontend/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # API Backend
    location /api {
        alias /var/www/ocr-system/backend/public;
        try_files $uri $uri/ /api/index.php?$query_string;

        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
            fastcgi_param SCRIPT_FILENAME /var/www/ocr-system/backend/public/index.php;
        }
    }

    # Bloquer l'accès aux fichiers sensibles
    location ~ /\. {
        deny all;
    }

    location ~ /\.env {
        deny all;
    }

    # Limite de taille d'upload
    client_max_body_size 10M;

    # Logs
    access_log /var/log/nginx/ocr-access.log;
    error_log /var/log/nginx/ocr-error.log;
}
```

```bash
# Activer le site
sudo ln -s /etc/nginx/sites-available/ocr-system /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## Configuration SSL/TLS

### Avec Let's Encrypt (gratuit)

```bash
# Obtenir un certificat
sudo certbot --nginx -d ocr.votre-domaine.com

# Renouvellement automatique
sudo certbot renew --dry-run

# Cron pour renouvellement
echo "0 3 * * * certbot renew --quiet && systemctl reload nginx" | sudo crontab -
```

---

## Déploiement Docker

### 1. Configuration Production

Créer `docker-compose.prod.yml` :
```yaml
version: '3.8'

services:
  mysql:
    image: mysql:8.0
    container_name: ocr_mysql
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      MYSQL_DATABASE: ocr_documents
      MYSQL_USER: ocr_user
      MYSQL_PASSWORD: ${MYSQL_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./database/schema.sql:/docker-entrypoint-initdb.d/schema.sql
    networks:
      - ocr_network

  backend:
    build:
      context: ./backend
      dockerfile: Dockerfile.prod
    container_name: ocr_backend
    restart: always
    volumes:
      - ./backend/storage:/var/www/html/storage
    environment:
      APP_ENV: production
      APP_DEBUG: "false"
    depends_on:
      - mysql
    networks:
      - ocr_network

  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile.prod
    container_name: ocr_frontend
    restart: always
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./ssl:/etc/nginx/ssl
    depends_on:
      - backend
    networks:
      - ocr_network

volumes:
  mysql_data:

networks:
  ocr_network:
    driver: bridge
```

### 2. Lancement

```bash
# Variables d'environnement
export MYSQL_ROOT_PASSWORD="strong_root_password"
export MYSQL_PASSWORD="strong_user_password"

# Démarrage
docker-compose -f docker-compose.prod.yml up -d

# Vérification
docker-compose -f docker-compose.prod.yml ps
docker-compose -f docker-compose.prod.yml logs -f
```

---

## Optimisation des performances

### 1. PHP-FPM

```bash
sudo nano /etc/php/8.1/fpm/pool.d/www.conf
```

```ini
[www]
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 10M
php_admin_value[post_max_size] = 10M
```

```bash
sudo systemctl restart php8.1-fpm
```

### 2. OpCache

```bash
sudo nano /etc/php/8.1/fpm/conf.d/10-opcache.ini
```

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
```

### 3. Nginx Cache

Ajouter dans la configuration Nginx :
```nginx
# Cache statique
location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff2)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

---

## Monitoring et logs

### 1. Logs d'application

```bash
# Rotation des logs
sudo nano /etc/logrotate.d/ocr-system
```

```
/var/www/ocr-system/backend/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
}
```

### 2. Monitoring avec cron

```bash
# Vérification de santé
crontab -e
```

```cron
# Nettoyage automatique (RGPD)
0 2 * * * /usr/bin/php /var/www/ocr-system/backend/scripts/cleanup_expired_documents.php

# Vérification santé
*/5 * * * * curl -f http://localhost/api/health || echo "API down!" | mail -s "OCR System Alert" admin@example.com
```

---

## Sauvegardes

### Script de sauvegarde

```bash
sudo nano /usr/local/bin/backup-ocr.sh
```

```bash
#!/bin/bash

BACKUP_DIR="/backups/ocr"
DATE=$(date +%Y%m%d_%H%M%S)

# Base de données
mysqldump -u ocr_user -p'PASSWORD' ocr_documents | gzip > "$BACKUP_DIR/db_$DATE.sql.gz"

# Fichiers
tar -czf "$BACKUP_DIR/files_$DATE.tar.gz" /var/www/ocr-system/backend/storage/app/documents

# Supprimer les anciennes sauvegardes (> 30 jours)
find $BACKUP_DIR -name "*.gz" -mtime +30 -delete

echo "Backup completed: $DATE"
```

```bash
sudo chmod +x /usr/local/bin/backup-ocr.sh

# Cron quotidien
echo "0 1 * * * /usr/local/bin/backup-ocr.sh" | sudo crontab -
```

---

## Conformité RGPD

### Checklist de conformité

- ✅ **Chiffrement** : Données sensibles chiffrées (AES-256)
- ✅ **Rétention limitée** : Suppression automatique après 30 jours
- ✅ **Droit à l'oubli** : Endpoint de suppression de compte
- ✅ **Portabilité** : Export JSON des données
- ✅ **Logs d'audit** : Traçabilité complète
- ✅ **HTTPS obligatoire** : TLS 1.2+
- ✅ **Minimisation** : Seules données nécessaires collectées

### Tests de conformité

```bash
# Vérifier le chiffrement HTTPS
curl -I https://ocr.votre-domaine.com

# Vérifier l'endpoint de santé
curl https://ocr.votre-domaine.com/api/health

# Vérifier les logs d'audit
tail -f /var/www/ocr-system/backend/storage/logs/audit.log
```

---

## Mise à jour en production

```bash
# 1. Backup
/usr/local/bin/backup-ocr.sh

# 2. Mode maintenance (optionnel)
sudo touch /var/www/ocr-system/backend/storage/down

# 3. Pull dernières modifications
cd /var/www/ocr-system
sudo -u www-data git pull

# 4. Mise à jour dépendances
cd backend
sudo -u www-data composer install --no-dev --optimize-autoloader

cd ../frontend
npm ci && npm run build

# 5. Migration base de données (si nécessaire)
mysql -u ocr_user -p ocr_documents < migrations/xxx.sql

# 6. Clear cache
sudo systemctl reload php8.1-fpm
sudo systemctl reload nginx

# 7. Retirer mode maintenance
sudo rm /var/www/ocr-system/backend/storage/down
```

---

## Support et maintenance

### Contacts
- Email support : support@votre-domaine.com
- Documentation : https://docs.votre-domaine.com
- Status page : https://status.votre-domaine.com

### SLA recommandés
- **Disponibilité** : 99.9% uptime
- **Temps de réponse** : < 500ms (API)
- **Support** : 24/7 pour incidents critiques
