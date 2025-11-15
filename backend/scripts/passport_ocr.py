#!/usr/bin/env python3
"""
Script PassportEye pour extraction de données MRZ (Machine Readable Zone)
des passeports et cartes d'identité
"""

import sys
import json
from passporteye import read_mrz

def extract_passport_data(image_path):
    """
    Extraire les données MRZ d'un passeport
    """
    try:
        # Lire le MRZ avec PassportEye
        mrz = read_mrz(image_path)

        if mrz is None:
            return {
                'success': False,
                'error': 'Aucun MRZ détecté dans l\'image'
            }

        # Extraire les données
        mrz_data = mrz.to_dict()

        # Formater le résultat
        result = {
            'success': True,
            'data': {
                'mrz_type': mrz_data.get('type', 'unknown'),
                'mrz_code': mrz_data.get('raw_text', ''),
                'valid': mrz_data.get('valid', False),
                'valid_score': mrz_data.get('valid_score', 0),

                # Informations personnelles
                'surname': mrz_data.get('surname', ''),
                'names': mrz_data.get('names', ''),
                'country': mrz_data.get('country', ''),
                'nationality': mrz_data.get('nationality', ''),
                'sex': mrz_data.get('sex', ''),

                # Numéros et dates
                'number': mrz_data.get('number', ''),
                'date_of_birth': mrz_data.get('date_of_birth', ''),
                'expiration_date': mrz_data.get('expiration_date', ''),

                # Données supplémentaires
                'personal_number': mrz_data.get('personal_number', ''),
                'check_number': mrz_data.get('check_number', ''),
                'check_date_of_birth': mrz_data.get('check_date_of_birth', ''),
                'check_expiration_date': mrz_data.get('check_expiration_date', ''),
                'check_personal_number': mrz_data.get('check_personal_number', ''),
                'check_composite': mrz_data.get('check_composite', ''),
            }
        }

        return result

    except FileNotFoundError:
        return {
            'success': False,
            'error': f'Fichier non trouvé: {image_path}'
        }
    except Exception as e:
        return {
            'success': False,
            'error': f'Erreur PassportEye: {str(e)}'
        }

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({
            'success': False,
            'error': 'Usage: python passport_ocr.py <image_path>'
        }))
        sys.exit(1)

    image_path = sys.argv[1]
    result = extract_passport_data(image_path)

    # Retourner le résultat en JSON
    print(json.dumps(result, ensure_ascii=False, indent=2))
