<?php

namespace Tests\Unit;

use App\Services\MrzRelaxService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour MrzRelaxService
 */
class MrzRelaxServiceTest extends TestCase
{
    private MrzRelaxService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MrzRelaxService();
    }

    /**
     * Test de correction MRZ pour un passeport TD3 avec erreurs OCR courantes
     */
    public function testCorrectMrzDataTD3WithCommonErrors()
    {
        // Données MRZ avec erreurs OCR (0->O, 1->I remplacés par erreur)
        $mrzData = [
            'mrz_code' => "P<FRADUP0NT<<JE4N<<<<<<<<<<<<<<<<<<<<<<<<<<\n" .
                          "12AB345671FR49001151M3005151234567890123456<1"
        ];

        $result = $this->service->correctMrzData($mrzData);

        $this->assertTrue($result['success']);
        $this->assertEquals('TD3', $result['mrz_type']);
        $this->assertArrayHasKey('corrections_applied', $result);
        $this->assertArrayHasKey('corrections_details', $result);
        $this->assertArrayHasKey('checksum_validation', $result);

        // Vérifier que les corrections ont été appliquées
        $correctedMrz = $result['corrected_mrz_code'];
        $this->assertStringContainsString('DUPONT', $correctedMrz); // O corrigé depuis 0
        $this->assertStringContainsString('JEAN', $correctedMrz);   // A corrigé depuis 4
    }

    /**
     * Test de correction MRZ pour un passeport TD3 sans erreurs
     */
    public function testCorrectMrzDataTD3NoErrors()
    {
        // Données MRZ correctes
        $mrzData = [
            'mrz_code' => "P<FRADUPONT<<JEAN<<<<<<<<<<<<<<<<<<<<<<<<<<\n" .
                          "12AB34567IFR4900115IM30051512345678901234560"
        ];

        $result = $this->service->correctMrzData($mrzData);

        $this->assertTrue($result['success']);
        $this->assertEquals('TD3', $result['mrz_type']);
        $this->assertEquals(0, $result['corrections_applied']); // Aucune correction nécessaire
    }

    /**
     * Test de correction avec champs numériques contenant des lettres
     */
    public function testCorrectNumericFieldsWithLetters()
    {
        // Date de naissance avec erreur: 49OO115 au lieu de 490011S
        // O -> 0, S reste S dans les champs numériques
        $mrzData = [
            'mrz_code' => "P<FRADUPONT<<JEAN<<<<<<<<<<<<<<<<<<<<<<<<<<\n" .
                          "12AB34567IFR49OO1I5IM3OO5I512345678901234560"
        ];

        $result = $this->service->correctMrzData($mrzData);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['corrections_applied']);

        // Vérifier que les corrections numériques ont été appliquées
        $corrections = $result['corrections_details'];
        $numericCorrections = array_filter($corrections, function ($c) {
            return $c['type'] === 'numeric';
        });

        $this->assertNotEmpty($numericCorrections);
    }

    /**
     * Test de correction avec champs alphabétiques contenant des chiffres
     */
    public function testCorrectAlphaFieldsWithDigits()
    {
        // Nom avec chiffres: DUP0NT au lieu de DUPONT
        $mrzData = [
            'mrz_code' => "P<FRADUP0NT<<JE4N<<<<<<<<<<<<<<<<<<<<<<<<<<\n" .
                          "12AB34567IFR4900115IM30051512345678901234560"
        ];

        $result = $this->service->correctMrzData($mrzData);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['corrections_applied']);

        // Vérifier que les corrections alpha ont été appliquées
        $corrections = $result['corrections_details'];
        $alphaCorrections = array_filter($corrections, function ($c) {
            return $c['type'] === 'alpha';
        });

        $this->assertNotEmpty($alphaCorrections);
    }

    /**
     * Test de détection de type MRZ TD1 (carte d'identité 3 lignes)
     */
    public function testDetectMrzTypeTD1()
    {
        // Format TD1: 3 lignes de 30 caractères
        $mrzData = [
            'mrz_code' => "I<FRAD23145890<<<<<<<<<<<<<\n" .
                          "4900115FM30051FRA<<<<<<<<<<<2\n" .
                          "DUPONT<<JEAN<<<<<<<<<<<<<<<"
        ];

        $result = $this->service->correctMrzData($mrzData);

        $this->assertTrue($result['success']);
        $this->assertEquals('TD1', $result['mrz_type']);
    }

    /**
     * Test de détection de type MRZ TD2 (carte d'identité 2 lignes)
     */
    public function testDetectMrzTypeTD2()
    {
        // Format TD2: 2 lignes de 36 caractères
        $mrzData = [
            'mrz_code' => "I<FRADUPONT<<JEAN<<<<<<<<<<<<<<<<<\n" .
                          "D23145890IFR4900115FM3005151234560"
        ];

        $result = $this->service->correctMrzData($mrzData);

        $this->assertTrue($result['success']);
        $this->assertEquals('TD2', $result['mrz_type']);
    }

    /**
     * Test avec données MRZ invalides (pas de code MRZ)
     */
    public function testCorrectMrzDataNoMrzCode()
    {
        $mrzData = [
            'some_field' => 'some_value'
        ];

        $result = $this->service->correctMrzData($mrzData);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('No MRZ code found in data', $result['error']);
    }

    /**
     * Test avec MRZ code vide
     */
    public function testCorrectMrzDataEmptyMrzCode()
    {
        $mrzData = [
            'mrz_code' => ''
        ];

        $result = $this->service->correctMrzData($mrzData);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test avec format MRZ inconnu
     */
    public function testCorrectMrzDataUnknownFormat()
    {
        // Format invalide: 4 lignes au lieu de 2 ou 3
        $mrzData = [
            'mrz_code' => "LINE1\nLINE2\nLINE3\nLINE4"
        ];

        $result = $this->service->correctMrzData($mrzData);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Unable to detect MRZ type', $result['error']);
    }

    /**
     * Test de validation des checksums
     */
    public function testChecksumValidation()
    {
        // Passeport TD3 avec checksums valides
        $mrzData = [
            'mrz_code' => "P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<\n" .
                          "L898902C36UTO7408122F1204159ZE184226B<<<<<10"
        ];

        $result = $this->service->correctMrzData($mrzData);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('checksum_validation', $result);

        $checksumValidation = $result['checksum_validation'];
        $this->assertArrayHasKey('valid', $checksumValidation);
        $this->assertArrayHasKey('checks', $checksumValidation);
    }

    /**
     * Test de corrections multiples dans une même ligne
     */
    public function testMultipleCorrectionsInSameLine()
    {
        // Plusieurs erreurs: 0->O, 1->I, 4->A
        $mrzData = [
            'mrz_code' => "P<FR4DUP0NT<<JE4N1<<<<<<<<<<<<<<<<<<<<<<<<<<<\n" .
                          "12AB3456715R490O115IM3OO51512345678901234560"
        ];

        $result = $this->service->correctMrzData($mrzData);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(5, $result['corrections_applied']); // Au moins 5 corrections
    }

    /**
     * Test de la structure du résultat de correction
     */
    public function testCorrectionResultStructure()
    {
        $mrzData = [
            'mrz_code' => "P<FRADUPONT<<JEAN<<<<<<<<<<<<<<<<<<<<<<<<<<\n" .
                          "12AB34567IFR4900115IM30051512345678901234560"
        ];

        $result = $this->service->correctMrzData($mrzData);

        // Vérifier la structure complète du résultat
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('mrz_type', $result);
        $this->assertArrayHasKey('original_mrz_code', $result);
        $this->assertArrayHasKey('corrected_mrz_code', $result);
        $this->assertArrayHasKey('corrections_applied', $result);
        $this->assertArrayHasKey('corrections_details', $result);
        $this->assertArrayHasKey('checksum_validation', $result);
        $this->assertArrayHasKey('data', $result);

        // Vérifier que les données originales sont fusionnées
        $this->assertArrayHasKey('mrz_code', $result['data']);
        $this->assertArrayHasKey('mrz_type', $result['data']);
    }

    /**
     * Test de correction avec cas limites
     */
    public function testEdgeCases()
    {
        // Test avec caractères de remplissage '<'
        $mrzData = [
            'mrz_code' => "P<FRADUPONT<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<\n" .
                          "12AB34567IFR4900115IM30051512345678901234560"
        ];

        $result = $this->service->correctMrzData($mrzData);

        $this->assertTrue($result['success']);
        // Les '<' ne doivent pas être corrigés
        $this->assertStringContainsString('<<', $result['corrected_mrz_code']);
    }
}
