# Changelog - Phase 1: Améliorations MRZ Scanner

## Version 2.1.0 - 2025-11-15

### 🚀 Nouvelles Fonctionnalités

#### 1. MrzRelaxService - Correction Intelligente MRZ
**Fichier**: `backend/app/Services/MrzRelaxService.php`

Implémente l'algorithme de correction intelligente inspiré du projet alsenet-labs/mrz-scanner.

**Fonctionnalités**:
- Correction automatique des erreurs OCR courantes dans les zones MRZ
- Mappings contextuels (0↔O, 1↔I, S↔5, B↔8, etc.)
- Support des 3 formats MRZ:
  - **TD1**: Cartes d'identité (3 lignes × 30 caractères)
  - **TD2**: Cartes d'identité (2 lignes × 36 caractères)
  - **TD3**: Passeports (2 lignes × 44 caractères)
- Validation des checksums ICAO 9303
- Détection automatique du type de document

**Corrections appliquées**:
- Champs alphabétiques: `0→O`, `1→I`, `2→Z`, `3→B`, `4→A`, `5→S`, `6→G`, `8→B`, `9→G`
- Champs numériques: `O→0`, `Q→0`, `D→0`, `I→1`, `L→1`, `Z→2`, `B→8`, `S→5`, `G→6`

**Gains attendus**: +10-15% de précision sur l'extraction MRZ

**Exemple d'utilisation**:
```php
$mrzRelaxService = new MrzRelaxService();
$result = $mrzRelaxService->correctMrzData($mrzData);

// Résultat:
// [
//     'success' => true,
//     'mrz_type' => 'TD3',
//     'corrections_applied' => 5,
//     'corrections_details' => [...],
//     'checksum_validation' => ['valid' => true, ...]
// ]
```

---

#### 2. OcrCacheService - Cache des Résultats OCR
**Fichier**: `backend/app/Services/OcrCacheService.php`

Système de cache basé sur le hash SHA256 des images pour éviter le retraitement.

**Fonctionnalités**:
- Hash SHA256 unique par image
- Clé de cache composite: `{hash}_{engine}_{language}`
- TTL configurable (défaut: 1 heure)
- Nettoyage automatique des caches expirés
- Statistiques détaillées du cache

**Gains attendus**: -50% de temps de traitement sur images identiques (cache hit rate ~40-50%)

**Configuration** (`.env`):
```env
OCR_CACHE_ENABLED=true
OCR_CACHE_DIR=storage/cache/ocr
OCR_CACHE_TTL=3600
```

**API**:
```php
$ocrCache = new OcrCacheService();

// Générer hash
$hash = $ocrCache->generateImageHash($imagePath);

// Vérifier existence
if ($ocrCache->has($hash, 'tesseract', 'fra+eng')) {
    $result = $ocrCache->get($hash, 'tesseract', 'fra+eng');
}

// Stocker résultat
$ocrCache->set($hash, 'tesseract', $ocrResult, 'fra+eng');

// Statistiques
$stats = $ocrCache->getStats();
```

---

#### 3. AutoRotationService - Détection et Correction d'Orientation
**Fichier**: `backend/app/Services/AutoRotationService.php`

Utilise Tesseract PSM 0 (OSD - Orientation and Script Detection) pour détecter et corriger automatiquement l'orientation des documents.

**Fonctionnalités**:
- Détection automatique de l'orientation (0°, 90°, 180°, 270°)
- Score de confiance de détection
- Rotation automatique si confiance > seuil
- Support JPEG, PNG, GIF
- Détection du script (Latin, Arabe, etc.)

**Gains attendus**: +5-10% de taux de succès OCR

**Configuration** (`.env`):
```env
AUTO_ROTATION_ENABLED=true
AUTO_ROTATION_CONFIDENCE_THRESHOLD=1.5
```

**Exemple d'utilisation**:
```php
$autoRotation = new AutoRotationService();

// Détecter orientation
$orientation = $autoRotation->detectOrientation($imagePath);
// {
//     'success' => true,
//     'orientation' => 0,
//     'rotate' => 90,  // Rotation nécessaire
//     'orientation_confidence' => 12.5
// }

// Détecter et corriger automatiquement
$result = $autoRotation->detectAndRotate($imagePath);
// {
//     'success' => true,
//     'rotated' => true,
//     'rotate_angle' => 90,
//     'confidence' => 12.5
// }
```

---

### 🔧 Modifications des Services Existants

#### PassportEyeService
**Fichier**: `backend/app/Services/PassportEyeService.php`

**Changements**:
- Intégration de `MrzRelaxService` dans le constructeur
- Application automatique des corrections MRZ après extraction PassportEye
- Logging des corrections appliquées
- Ajout du champ `mrz_corrections` dans le résultat

**Avant**:
```php
$result = $this->passportEye->extractPassportData($imagePath);
// Résultat brut de PassportEye
```

**Après**:
```php
$result = $this->passportEye->extractPassportData($imagePath);
// Résultat avec corrections MRZ automatiques
// + mrz_corrections: {applied, details, checksum_validation}
```

---

#### DocumentController
**Fichier**: `backend/app/Controllers/DocumentController.php`

**Changements**:
- Intégration de `OcrCacheService` dans le constructeur
- Vérification du cache avant OCR
- Stockage des résultats dans le cache après OCR
- Logging des cache hits/misses
- Génération du hash de l'image prétraitée

**Flow mis à jour**:
```
1. Prétraitement image → processedPath
2. Génération hash SHA256
3. Vérification cache
   ├─ Cache HIT → Retour immédiat
   └─ Cache MISS → OCR + Stockage cache
4. Sauvegarde en base de données
```

---

#### ImageProcessingService
**Fichier**: `backend/app/Services/ImageProcessingService.php`

**Changements**:
- Intégration de `AutoRotationService` dans le constructeur
- Auto-rotation automatique dans `preprocessImage()` (si activée)
- Implémentation complète de la méthode `autoRotate()`
- Logging des rotations appliquées

**Pipeline de prétraitement mis à jour**:
```
1. Auto-rotation (si AUTO_ROTATION_ENABLED=true)
2. Conversion niveaux de gris
3. Augmentation contraste
4. Ajustement luminosité
5. Sauvegarde JPEG 95%
```

---

### 📊 Tests Unitaires

#### MrzRelaxServiceTest
**Fichier**: `backend/tests/Unit/MrzRelaxServiceTest.php`

**Couverture**:
- ✅ Correction MRZ TD3 avec erreurs OCR courantes
- ✅ Correction MRZ TD3 sans erreurs
- ✅ Correction champs numériques avec lettres
- ✅ Correction champs alphabétiques avec chiffres
- ✅ Détection type MRZ TD1
- ✅ Détection type MRZ TD2
- ✅ Gestion erreurs (MRZ manquant, vide, format inconnu)
- ✅ Validation des checksums
- ✅ Corrections multiples dans une même ligne
- ✅ Structure du résultat de correction
- ✅ Cas limites (caractères '<')

**Exécution**:
```bash
cd backend
./vendor/bin/phpunit tests/Unit/MrzRelaxServiceTest.php
```

---

### ⚙️ Configuration

#### Nouvelles Variables d'Environnement
**Fichier**: `backend/.env.example`

```env
# OCR Cache Configuration
OCR_CACHE_ENABLED=true              # Activer/désactiver le cache
OCR_CACHE_DIR=storage/cache/ocr     # Répertoire de stockage
OCR_CACHE_TTL=3600                  # Durée de vie (secondes)

# Auto-Rotation Configuration
AUTO_ROTATION_ENABLED=true                    # Activer/désactiver
AUTO_ROTATION_CONFIDENCE_THRESHOLD=1.5        # Seuil de confiance minimum
```

---

### 📈 Gains de Performance Attendus

| Métrique | Avant | Après Phase 1 | Amélioration |
|----------|-------|---------------|--------------|
| **Temps OCR MRZ** | 3-8s | 2-5s | **-40%** |
| **Précision MRZ** | 85% | 92-95% | **+10-15%** |
| **Cache Hit Rate** | 0% | 40-50% | **+50%** |
| **Taux Succès Auto-Rotation** | 0% | 95%+ | **+95%** |
| **Temps UI Blocage** | 100-150ms | < 50ms | **Prévu Phase 2** |

---

### 🔍 Structure des Fichiers Créés

```
backend/
├── app/
│   ├── Controllers/
│   │   └── DocumentController.php           [MODIFIÉ]
│   └── Services/
│       ├── MrzRelaxService.php               [NOUVEAU]
│       ├── OcrCacheService.php               [NOUVEAU]
│       ├── AutoRotationService.php           [NOUVEAU]
│       ├── PassportEyeService.php            [MODIFIÉ]
│       └── ImageProcessingService.php        [MODIFIÉ]
└── tests/
    └── Unit/
        └── MrzRelaxServiceTest.php           [NOUVEAU]

.env.example                                  [MODIFIÉ]
CHANGELOG_PHASE1.md                           [NOUVEAU]
```

---

### 📝 Documentation Complémentaire

Les rapports d'analyse détaillés sont disponibles dans les fichiers suivants:

- **INDEX_RAPPORT_ANALYSE.md**: Guide de navigation
- **RAPPORT_EXECUTIVE_SUMMARY.md**: Vue d'ensemble exécutive et plan d'action
- **ANALYSE_MRZ_SCANNER.md**: Analyse technique complète comparative (913 lignes)
- **IMPLEMENTATION_MRZ_RELAX.md**: Guide d'implémentation avec code (998 lignes)

---

### 🚧 Prochaines Étapes (Phase 2)

**Planifié pour la Phase 2** (3-4 semaines):

1. **Web Workers Frontend**
   - Traitement d'image côté client non-bloquant
   - Gain UX: +100-200ms responsiveness

2. **Optimisation Tesseract PSM**
   - PSM adaptatif par type de document
   - PSM 11 pour passeports (sparse text)
   - PSM 3 pour documents généraux

3. **ML léger pour détection document**
   - Alternative légère Dynamsoft
   - ONNX.js model
   - Réduction dépendances

---

### ✅ Checklist de Déploiement

Avant de déployer en production:

- [ ] Copier `.env.example` vers `.env` et configurer les nouvelles variables
- [ ] Créer le répertoire de cache: `mkdir -p backend/storage/cache/ocr`
- [ ] Vérifier les permissions: `chmod -R 775 backend/storage/cache`
- [ ] Exécuter les tests: `./vendor/bin/phpunit`
- [ ] Vérifier que Tesseract supporte PSM 0: `tesseract --help-psm`
- [ ] Valider la configuration du cache
- [ ] Tester sur images réelles
- [ ] Monitorer les logs pour les cache hits/misses
- [ ] Valider les métriques de performance

---

### 🐛 Issues Connues

Aucune issue connue pour le moment.

---

### 👥 Contributeurs

- **Analyse & Implémentation**: Claude Code (Assistant IA)
- **Inspiration**: [alsenet-labs/mrz-scanner](https://github.com/alsenet-labs/mrz-scanner)
- **Revue**: À venir

---

### 📜 Licence

Même licence que le projet principal OCR Version 2.

---

**Date de Release**: 15 Novembre 2025
**Version**: 2.1.0
**Phase**: 1/3
