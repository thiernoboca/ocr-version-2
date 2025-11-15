FROM node:18-alpine

WORKDIR /app

# Copier les fichiers de dépendances
COPY package*.json ./

# Installer les dépendances
RUN npm install

# Copier les fichiers de l'application
COPY . .

# Exposer le port
EXPOSE 3000

# Commande de démarrage
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
