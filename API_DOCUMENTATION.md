# Documentation API Factur-X

Cette API REST permet d'intégrer le XML Factur-X (profil EN16931) dans des **PDF existants** ou de générer le XML seul, sans passer par l'interface web. Elle est entièrement **sans état** : aucune donnée n'est sauvegardée en base.

## Base URL

```
http://localhost:8000
```

---

## Endpoints

### `POST /api/facturx/embed`

Embarque un XML Factur-X EN16931 dans un PDF fourni et retourne le PDF enrichi.

**Content-Type de la requête :** `multipart/form-data`

| Champ     | Type   | Obligatoire | Description                                              |
|-----------|--------|-------------|----------------------------------------------------------|
| `pdf`     | file   | Oui         | Fichier PDF source (votre facture visuelle existante)    |
| `invoice` | string | Oui         | Objet JSON décrivant la facture (voir structure ci-dessous) |

**Réponse en cas de succès :** `200 OK`
- `Content-Type: application/pdf`
- Corps : contenu binaire du PDF Factur-X

**Erreurs possibles :**
| Code | Cause                                        |
|------|----------------------------------------------|
| 400  | Champ `pdf` ou `invoice` manquant / JSON invalide |
| 422  | Champ obligatoire absent dans le JSON        |
| 500  | Erreur interne (PDF corrompu, etc.)          |

---

### `POST /api/facturx/xml`

Génère uniquement le XML Factur-X EN16931 sans PDF.

**Content-Type de la requête :** `application/json`

Le corps de la requête est directement l'objet JSON de la facture (voir structure ci-dessous).

**Réponse en cas de succès :** `200 OK`
- `Content-Type: application/xml; charset=UTF-8`
- Corps : contenu XML Factur-X

---

## Structure JSON de la facture

### Champs racine

| Champ             | Type   | Obligatoire | Description                                    |
|-------------------|--------|-------------|------------------------------------------------|
| `numeroFacture`   | string | Oui         | Numéro unique de facture (ex: `FAC-2024-001`)  |
| `dateFacture`     | string | Oui         | Date au format `YYYY-MM-DD`                    |
| `devise`          | string | Non         | Code ISO 4217 (défaut : `EUR`)                 |
| `typeFacture`     | string | Non         | `FA` (facture), `FC` (avoir) — défaut : `FA`   |
| `dateEcheance`    | string | Non         | Date d'échéance `YYYY-MM-DD`                   |
| `dateLivraison`   | string | Non         | Date de livraison `YYYY-MM-DD`                 |
| `commandeAcheteur`| string | Non         | Référence bon de commande acheteur             |
| `commentaire`     | string | Non         | Note libre incluse dans le XML                 |
| `fournisseur`     | object | Oui         | Vendeur (voir ci-dessous)                      |
| `acheteur`        | object | Oui         | Acheteur (voir ci-dessous)                     |
| `lignes`          | array  | Oui         | Lignes de facturation (voir ci-dessous)        |
| `allowances`      | array  | Non         | Remises / frais au niveau document             |
| `paymentMeans`    | array  | Non         | Moyens de paiement UNTDID 4461                 |

### Objet `fournisseur` / `acheteur`

| Champ       | Type   | Obligatoire | Description                          |
|-------------|--------|-------------|--------------------------------------|
| `nom`       | string | Oui         | Raison sociale                       |
| `siren`     | string | Non         | Numéro SIREN (9 chiffres)            |
| `siret`     | string | Non         | Numéro SIRET (14 chiffres)           |
| `numeroTva` | string | Non         | Numéro TVA intracommunautaire        |
| `adresse`   | string | Non         | Adresse postale ligne 1              |
| `ville`     | string | Non         | Ville                                |
| `codePostal`| string | Non         | Code postal                          |
| `codePays`  | string | Non         | Code ISO 3166-1 alpha-2 (défaut `FR`)|
| `email`     | string | Non         | Adresse email                        |

### Objet ligne (`lignes[]`)

| Champ           | Type   | Obligatoire | Description                              |
|-----------------|--------|-------------|------------------------------------------|
| `designation`   | string | Oui         | Désignation du produit/service           |
| `quantite`      | number | Oui         | Quantité                                 |
| `prixUnitaireHT`| number | Oui         | Prix unitaire hors taxes                 |
| `tauxTVA`       | number | Oui         | Taux de TVA en % (ex: `20`, `5.5`, `0`) |
| `unite`         | string | Non         | Code unité UN/ECE (défaut : `H87` = pièce)|

Les montants HT, TVA et TTC sont calculés automatiquement côté serveur.

### Objet allowance/charge (`allowances[]`)

| Champ      | Type    | Obligatoire | Description                                   |
|------------|---------|-------------|-----------------------------------------------|
| `amount`   | number  | Oui         | Montant HT de la remise ou du frais           |
| `isCharge` | boolean | Non         | `true` = frais, `false` = remise (défaut)     |
| `taxRate`  | number  | Non         | Taux de TVA applicable à ce montant           |
| `reason`   | string  | Non         | Libellé de la remise/frais                    |

### Objet moyen de paiement (`paymentMeans[]`)

| Champ         | Type   | Obligatoire | Description                                         |
|---------------|--------|-------------|-----------------------------------------------------|
| `code`        | string | Oui         | Code UNTDID 4461 (ex: `30`=virement, `58`=SEPA, `42`=virement bancaire) |
| `information` | string | Non         | IBAN ou information complémentaire                  |

---

## Exemple complet

### Requête `POST /api/facturx/embed` (curl)

```bash
curl -X POST http://localhost:8000/api/facturx/embed \
  -F "pdf=@/chemin/vers/ma_facture.pdf" \
  -F 'invoice={
    "numeroFacture": "FAC-2024-001",
    "dateFacture": "2024-03-15",
    "devise": "EUR",
    "dateEcheance": "2024-04-15",
    "commentaire": "Merci pour votre confiance",
    "fournisseur": {
      "nom": "Ma Société SAS",
      "siret": "12345678901234",
      "numeroTva": "FR12345678901",
      "adresse": "10 rue de la Paix",
      "ville": "Paris",
      "codePostal": "75001",
      "codePays": "FR"
    },
    "acheteur": {
      "nom": "Client SARL",
      "siren": "987654321",
      "adresse": "5 avenue des Fleurs",
      "ville": "Lyon",
      "codePostal": "69001",
      "codePays": "FR"
    },
    "lignes": [
      {
        "designation": "Prestation de conseil",
        "quantite": 3,
        "prixUnitaireHT": 800.00,
        "tauxTVA": 20,
        "unite": "HUR"
      },
      {
        "designation": "Frais de déplacement",
        "quantite": 1,
        "prixUnitaireHT": 150.00,
        "tauxTVA": 20
      }
    ],
    "allowances": [
      {
        "amount": 100.00,
        "isCharge": false,
        "taxRate": 20,
        "reason": "Remise commerciale 10%"
      }
    ],
    "paymentMeans": [
      {
        "code": "58",
        "information": "FR7630006000011234567890189"
      }
    ]
  }' \
  --output facture_facturx.pdf
```

### Requête `POST /api/facturx/xml` (curl)

```bash
curl -X POST http://localhost:8000/api/facturx/xml \
  -H "Content-Type: application/json" \
  -d '{
    "numeroFacture": "FAC-2024-001",
    "dateFacture": "2024-03-15",
    "devise": "EUR",
    "fournisseur": { "nom": "Ma Société SAS", "siret": "12345678901234" },
    "acheteur": { "nom": "Client SARL" },
    "lignes": [
      { "designation": "Prestation", "quantite": 1, "prixUnitaireHT": 1000.00, "tauxTVA": 20 }
    ]
  }' \
  --output facture_fx.xml
```

### Exemple PHP (Guzzle)

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Utils;

$client = new Client(['base_uri' => 'http://localhost:8000']);

$invoice = [
    'numeroFacture' => 'FAC-2024-001',
    'dateFacture'   => '2024-03-15',
    'devise'        => 'EUR',
    'fournisseur'   => ['nom' => 'Ma Société SAS', 'siret' => '12345678901234'],
    'acheteur'      => ['nom' => 'Client SARL'],
    'lignes'        => [
        ['designation' => 'Prestation', 'quantite' => 1, 'prixUnitaireHT' => 1000.00, 'tauxTVA' => 20],
    ],
];

$response = $client->post('/api/facturx/embed', [
    'multipart' => [
        ['name' => 'pdf',     'contents' => Utils::tryFopen('/chemin/facture.pdf', 'r'), 'filename' => 'facture.pdf'],
        ['name' => 'invoice', 'contents' => json_encode($invoice)],
    ],
]);

file_put_contents('facture_facturx.pdf', $response->getBody());
```

### Exemple JavaScript (fetch / Node.js)

```javascript
const fs = require('fs');
const FormData = require('form-data');
const fetch = require('node-fetch');

const invoice = {
  numeroFacture: 'FAC-2024-001',
  dateFacture: '2024-03-15',
  devise: 'EUR',
  fournisseur: { nom: 'Ma Société SAS', siret: '12345678901234' },
  acheteur: { nom: 'Client SARL' },
  lignes: [
    { designation: 'Prestation', quantite: 1, prixUnitaireHT: 1000.00, tauxTVA: 20 },
  ],
};

const form = new FormData();
form.append('pdf', fs.createReadStream('./facture.pdf'), 'facture.pdf');
form.append('invoice', JSON.stringify(invoice));

const response = await fetch('http://localhost:8000/api/facturx/embed', {
  method: 'POST',
  body: form,
});

fs.writeFileSync('facture_facturx.pdf', Buffer.from(await response.arrayBuffer()));
```

---

## Codes unités (UN/ECE Rec 20) courants

| Code | Unité              |
|------|--------------------|
| `H87`| Pièce (défaut)     |
| `HUR`| Heure              |
| `DAY`| Jour               |
| `MON`| Mois               |
| `KGM`| Kilogramme         |
| `MTR`| Mètre              |
| `LTR`| Litre              |

## Codes UNTDID 4461 courants

| Code | Moyen de paiement   |
|------|---------------------|
| `30` | Virement            |
| `42` | Virement bancaire   |
| `48` | Carte bancaire      |
| `49` | Prélèvement         |
| `57` | Chèque              |
| `58` | Virement SEPA       |
