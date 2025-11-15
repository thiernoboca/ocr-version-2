<?php

namespace App\Services;

/**
 * MrzRelaxService - Correction intelligente des données MRZ
 *
 * Inspiré de l'algorithme mrz-relax du projet alsenet-labs/mrz-scanner
 * Corrige automatiquement les erreurs OCR courantes dans les zones MRZ
 *
 * @package App\Services
 */
class MrzRelaxService
{
    /**
     * Mappings de correction pour les caractères alphabétiques (A-Z)
     * Correction contexte-aware: 0→O, 1→I, etc.
     */
    private const ALPHA_CORRECTIONS = [
        '0' => 'O',
        '1' => 'I',
        '2' => 'Z',
        '3' => 'B',
        '4' => 'A',
        '5' => 'S',
        '6' => 'G',
        '8' => 'B',
        '9' => 'G'
    ];

    /**
     * Mappings de correction pour les caractères numériques (0-9)
     * Correction contexte-aware: O→0, I→1, etc.
     */
    private const NUMERIC_CORRECTIONS = [
        'O' => '0',
        'Q' => '0',
        'D' => '0',
        'I' => '1',
        'L' => '1',
        'Z' => '2',
        'B' => '8',
        'S' => '5',
        'G' => '6'
    ];

    /**
     * Définitions des champs MRZ selon le format
     * Format TD1 (ID cards - 3 lignes de 30 caractères)
     * Format TD2 (ID cards - 2 lignes de 36 caractères)
     * Format TD3 (Passports - 2 lignes de 44 caractères)
     */
    private const MRZ_FIELD_DEFINITIONS = [
        'TD1' => [
            'line1' => [
                ['name' => 'document_type', 'start' => 0, 'length' => 2, 'type' => 'alpha'],
                ['name' => 'country_code', 'start' => 2, 'length' => 3, 'type' => 'alpha'],
                ['name' => 'document_number', 'start' => 5, 'length' => 9, 'type' => 'alphanumeric'],
                ['name' => 'check_document_number', 'start' => 14, 'length' => 1, 'type' => 'numeric'],
                ['name' => 'optional_data_1', 'start' => 15, 'length' => 15, 'type' => 'alphanumeric']
            ],
            'line2' => [
                ['name' => 'date_of_birth', 'start' => 0, 'length' => 6, 'type' => 'numeric'],
                ['name' => 'check_date_of_birth', 'start' => 6, 'length' => 1, 'type' => 'numeric'],
                ['name' => 'sex', 'start' => 7, 'length' => 1, 'type' => 'alpha'],
                ['name' => 'expiration_date', 'start' => 8, 'length' => 6, 'type' => 'numeric'],
                ['name' => 'check_expiration_date', 'start' => 14, 'length' => 1, 'type' => 'numeric'],
                ['name' => 'nationality', 'start' => 15, 'length' => 3, 'type' => 'alpha'],
                ['name' => 'optional_data_2', 'start' => 18, 'length' => 11, 'type' => 'alphanumeric'],
                ['name' => 'check_composite', 'start' => 29, 'length' => 1, 'type' => 'numeric']
            ],
            'line3' => [
                ['name' => 'names', 'start' => 0, 'length' => 30, 'type' => 'alpha']
            ]
        ],
        'TD2' => [
            'line1' => [
                ['name' => 'document_type', 'start' => 0, 'length' => 2, 'type' => 'alpha'],
                ['name' => 'country_code', 'start' => 2, 'length' => 3, 'type' => 'alpha'],
                ['name' => 'names', 'start' => 5, 'length' => 31, 'type' => 'alpha']
            ],
            'line2' => [
                ['name' => 'document_number', 'start' => 0, 'length' => 9, 'type' => 'alphanumeric'],
                ['name' => 'check_document_number', 'start' => 9, 'length' => 1, 'type' => 'numeric'],
                ['name' => 'nationality', 'start' => 10, 'length' => 3, 'type' => 'alpha'],
                ['name' => 'date_of_birth', 'start' => 13, 'length' => 6, 'type' => 'numeric'],
                ['name' => 'check_date_of_birth', 'start' => 19, 'length' => 1, 'type' => 'numeric'],
                ['name' => 'sex', 'start' => 20, 'length' => 1, 'type' => 'alpha'],
                ['name' => 'expiration_date', 'start' => 21, 'length' => 6, 'type' => 'numeric'],
                ['name' => 'check_expiration_date', 'start' => 27, 'length' => 1, 'type' => 'numeric'],
                ['name' => 'optional_data', 'start' => 28, 'length' => 7, 'type' => 'alphanumeric'],
                ['name' => 'check_composite', 'start' => 35, 'length' => 1, 'type' => 'numeric']
            ]
        ],
        'TD3' => [
            'line1' => [
                ['name' => 'document_type', 'start' => 0, 'length' => 1, 'type' => 'alpha'],
                ['name' => 'document_type_optional', 'start' => 1, 'length' => 1, 'type' => 'alpha'],
                ['name' => 'country_code', 'start' => 2, 'length' => 3, 'type' => 'alpha'],
                ['name' => 'names', 'start' => 5, 'length' => 39, 'type' => 'alpha']
            ],
            'line2' => [
                ['name' => 'document_number', 'start' => 0, 'length' => 9, 'type' => 'alphanumeric'],
                ['name' => 'check_document_number', 'start' => 9, 'length' => 1, 'type' => 'numeric'],
                ['name' => 'nationality', 'start' => 10, 'length' => 3, 'type' => 'alpha'],
                ['name' => 'date_of_birth', 'start' => 13, 'length' => 6, 'type' => 'numeric'],
                ['name' => 'check_date_of_birth', 'start' => 19, 'length' => 1, 'type' => 'numeric'],
                ['name' => 'sex', 'start' => 20, 'length' => 1, 'type' => 'alpha'],
                ['name' => 'expiration_date', 'start' => 21, 'length' => 6, 'type' => 'numeric'],
                ['name' => 'check_expiration_date', 'start' => 27, 'length' => 1, 'type' => 'numeric'],
                ['name' => 'personal_number', 'start' => 28, 'length' => 14, 'type' => 'alphanumeric'],
                ['name' => 'check_personal_number', 'start' => 42, 'length' => 1, 'type' => 'numeric'],
                ['name' => 'check_composite', 'start' => 43, 'length' => 1, 'type' => 'numeric']
            ]
        ]
    ];

    /**
     * Valeurs pour le calcul des checksums selon la norme ICAO
     */
    private const CHECKSUM_VALUES = [
        '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5,
        '6' => 6, '7' => 7, '8' => 8, '9' => 9, '<' => 0,
        'A' => 10, 'B' => 11, 'C' => 12, 'D' => 13, 'E' => 14, 'F' => 15,
        'G' => 16, 'H' => 17, 'I' => 18, 'J' => 19, 'K' => 20, 'L' => 21,
        'M' => 22, 'N' => 23, 'O' => 24, 'P' => 25, 'Q' => 26, 'R' => 27,
        'S' => 28, 'T' => 29, 'U' => 30, 'V' => 31, 'W' => 32, 'X' => 33,
        'Y' => 34, 'Z' => 35
    ];

    /**
     * Corrige les données MRZ extraites par PassportEye
     *
     * @param array $mrzData Données MRZ brutes de PassportEye
     * @return array Données MRZ corrigées avec métadonnées
     */
    public function correctMrzData(array $mrzData): array
    {
        if (!isset($mrzData['mrz_code']) || empty($mrzData['mrz_code'])) {
            return [
                'success' => false,
                'error' => 'No MRZ code found in data',
                'original_data' => $mrzData
            ];
        }

        // Détecter le type de MRZ
        $mrzType = $this->detectMrzType($mrzData['mrz_code']);

        if (!$mrzType) {
            return [
                'success' => false,
                'error' => 'Unable to detect MRZ type',
                'original_data' => $mrzData
            ];
        }

        // Séparer les lignes MRZ
        $lines = $this->splitMrzLines($mrzData['mrz_code'], $mrzType);

        // Appliquer les corrections ligne par ligne
        $correctedLines = [];
        $corrections = [];

        foreach ($lines as $lineNumber => $lineContent) {
            $lineKey = 'line' . ($lineNumber + 1);
            $correctedLine = $this->correctMrzLine(
                $lineContent,
                self::MRZ_FIELD_DEFINITIONS[$mrzType][$lineKey] ?? [],
                $corrections
            );
            $correctedLines[] = $correctedLine;
        }

        // Reconstituer le code MRZ corrigé
        $correctedMrzCode = implode("\n", $correctedLines);

        // Valider les checksums
        $checksumValidation = $this->validateChecksums($correctedLines, $mrzType);

        return [
            'success' => true,
            'mrz_type' => $mrzType,
            'original_mrz_code' => $mrzData['mrz_code'],
            'corrected_mrz_code' => $correctedMrzCode,
            'corrections_applied' => count($corrections),
            'corrections_details' => $corrections,
            'checksum_validation' => $checksumValidation,
            'data' => array_merge($mrzData, [
                'mrz_code' => $correctedMrzCode,
                'mrz_type' => $mrzType
            ])
        ];
    }

    /**
     * Détecte le type de MRZ basé sur le nombre de lignes et de caractères
     *
     * @param string $mrzCode Code MRZ brut
     * @return string|null Type MRZ (TD1, TD2, TD3) ou null
     */
    private function detectMrzType(string $mrzCode): ?string
    {
        $lines = explode("\n", trim($mrzCode));
        $lineCount = count($lines);

        if ($lineCount === 3 && strlen(trim($lines[0])) === 30) {
            return 'TD1'; // ID card format (3x30)
        }

        if ($lineCount === 2 && strlen(trim($lines[0])) === 36) {
            return 'TD2'; // ID card format (2x36)
        }

        if ($lineCount === 2 && strlen(trim($lines[0])) === 44) {
            return 'TD3'; // Passport format (2x44)
        }

        return null;
    }

    /**
     * Sépare les lignes MRZ
     *
     * @param string $mrzCode Code MRZ
     * @param string $mrzType Type MRZ
     * @return array Lignes MRZ
     */
    private function splitMrzLines(string $mrzCode, string $mrzType): array
    {
        $lines = explode("\n", trim($mrzCode));
        $expectedLineCount = ($mrzType === 'TD1') ? 3 : 2;

        // Assurer le bon nombre de lignes
        while (count($lines) < $expectedLineCount) {
            $lines[] = '';
        }

        return array_slice($lines, 0, $expectedLineCount);
    }

    /**
     * Corrige une ligne MRZ selon les définitions de champs
     *
     * @param string $line Ligne MRZ brute
     * @param array $fieldDefinitions Définitions des champs pour cette ligne
     * @param array &$corrections Référence au tableau des corrections
     * @return string Ligne MRZ corrigée
     */
    private function correctMrzLine(string $line, array $fieldDefinitions, array &$corrections): string
    {
        $correctedLine = $line;

        foreach ($fieldDefinitions as $field) {
            $start = $field['start'];
            $length = $field['length'];
            $type = $field['type'];
            $fieldName = $field['name'];

            // Extraire le contenu du champ
            $fieldContent = substr($correctedLine, $start, $length);

            // Appliquer les corrections selon le type
            $correctedContent = $this->correctField($fieldContent, $type, $fieldName, $corrections);

            // Remplacer dans la ligne
            $correctedLine = substr_replace($correctedLine, $correctedContent, $start, $length);
        }

        return $correctedLine;
    }

    /**
     * Corrige un champ MRZ selon son type
     *
     * @param string $content Contenu du champ
     * @param string $type Type du champ (alpha, numeric, alphanumeric)
     * @param string $fieldName Nom du champ
     * @param array &$corrections Référence au tableau des corrections
     * @return string Contenu corrigé
     */
    private function correctField(string $content, string $type, string $fieldName, array &$corrections): string
    {
        $correctedContent = '';

        for ($i = 0; $i < strlen($content); $i++) {
            $char = $content[$i];
            $correctedChar = $char;

            switch ($type) {
                case 'alpha':
                    // Champs alphabétiques: corriger les chiffres en lettres
                    if (isset(self::ALPHA_CORRECTIONS[$char])) {
                        $correctedChar = self::ALPHA_CORRECTIONS[$char];
                        $corrections[] = [
                            'field' => $fieldName,
                            'position' => $i,
                            'original' => $char,
                            'corrected' => $correctedChar,
                            'type' => 'alpha'
                        ];
                    }
                    break;

                case 'numeric':
                    // Champs numériques: corriger les lettres en chiffres
                    if (isset(self::NUMERIC_CORRECTIONS[$char])) {
                        $correctedChar = self::NUMERIC_CORRECTIONS[$char];
                        $corrections[] = [
                            'field' => $fieldName,
                            'position' => $i,
                            'original' => $char,
                            'corrected' => $correctedChar,
                            'type' => 'numeric'
                        ];
                    }
                    break;

                case 'alphanumeric':
                    // Champs alphanumériques: pas de correction automatique
                    // (trop risqué sans contexte supplémentaire)
                    break;
            }

            $correctedContent .= $correctedChar;
        }

        return $correctedContent;
    }

    /**
     * Calcule le checksum selon la norme ICAO 9303
     *
     * @param string $data Données pour le calcul
     * @return int Checksum (0-9)
     */
    private function calculateChecksum(string $data): int
    {
        $weights = [7, 3, 1]; // Poids ICAO
        $sum = 0;

        for ($i = 0; $i < strlen($data); $i++) {
            $char = strtoupper($data[$i]);
            $value = self::CHECKSUM_VALUES[$char] ?? 0;
            $weight = $weights[$i % 3];
            $sum += $value * $weight;
        }

        return $sum % 10;
    }

    /**
     * Valide les checksums de la MRZ
     *
     * @param array $lines Lignes MRZ corrigées
     * @param string $mrzType Type MRZ
     * @return array Résultats de validation
     */
    private function validateChecksums(array $lines, string $mrzType): array
    {
        $validation = [
            'valid' => true,
            'checks' => []
        ];

        $fieldDefs = self::MRZ_FIELD_DEFINITIONS[$mrzType];

        // Valider chaque checksum selon le type
        switch ($mrzType) {
            case 'TD3': // Passport
                $line2 = $lines[1] ?? '';

                // Document number
                $docNum = substr($line2, 0, 9);
                $checkDocNum = substr($line2, 9, 1);
                $validation['checks']['document_number'] = $this->validateSingleChecksum(
                    $docNum, $checkDocNum, 'Document Number'
                );

                // Date of birth
                $dob = substr($line2, 13, 6);
                $checkDob = substr($line2, 19, 1);
                $validation['checks']['date_of_birth'] = $this->validateSingleChecksum(
                    $dob, $checkDob, 'Date of Birth'
                );

                // Expiration date
                $expDate = substr($line2, 21, 6);
                $checkExpDate = substr($line2, 27, 1);
                $validation['checks']['expiration_date'] = $this->validateSingleChecksum(
                    $expDate, $checkExpDate, 'Expiration Date'
                );

                // Personal number
                $personalNum = substr($line2, 28, 14);
                $checkPersonalNum = substr($line2, 42, 1);
                $validation['checks']['personal_number'] = $this->validateSingleChecksum(
                    $personalNum, $checkPersonalNum, 'Personal Number'
                );

                // Composite checksum
                $composite = $docNum . $checkDocNum . $dob . $checkDob . $expDate . $checkExpDate . $personalNum . $checkPersonalNum;
                $checkComposite = substr($line2, 43, 1);
                $validation['checks']['composite'] = $this->validateSingleChecksum(
                    $composite, $checkComposite, 'Composite'
                );

                break;

            // Ajouter TD1 et TD2 si nécessaire
        }

        // Vérifier si toutes les validations ont réussi
        foreach ($validation['checks'] as $check) {
            if (!$check['valid']) {
                $validation['valid'] = false;
                break;
            }
        }

        return $validation;
    }

    /**
     * Valide un seul checksum
     *
     * @param string $data Données
     * @param string $expectedChecksum Checksum attendu
     * @param string $label Label pour le rapport
     * @return array Résultat de validation
     */
    private function validateSingleChecksum(string $data, string $expectedChecksum, string $label): array
    {
        $calculated = $this->calculateChecksum($data);
        $expected = (int)$expectedChecksum;

        return [
            'label' => $label,
            'valid' => $calculated === $expected,
            'expected' => $expected,
            'calculated' => $calculated,
            'data' => $data
        ];
    }
}
