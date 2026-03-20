# FacturxV2

**Application web de facturation électronique conforme Factur-X / ZUGFeRD (profil EN16931), construite avec Symfony 6.4 et PHP 8.1+.**

FacturxV2 permet de créer, gérer et exporter des factures au format PDF/A-3 avec un fichier XML embarqué (`factur-x.xml`), conforme à la norme européenne EN16931. L'application couvre l'ensemble du cycle : saisie des clients et lignes de facture, calcul automatique de la TVA multi-taux, gestion des remises/charges, et génération du PDF final avec XML intégré.

---

## Table des matières

1. [Contexte et objectif](#1-contexte-et-objectif)
2. [Stack technique](#2-stack-technique)
3. [Architecture globale](#3-architecture-globale)
4. [Modèle de données (Entités)](#4-modèle-de-données-entités)
5. [Contrôleurs et routes](#5-contrôleurs-et-routes)
6. [Services métier](#6-services-métier)
7. [Formulaires Symfony](#7-formulaires-symfony)
8. [Templates Twig](#8-templates-twig)
9. [API REST](#9-api-rest)
10. [Frontend (Stimulus / Turbo)](#10-frontend-stimulus--turbo)
11. [Base de données et migrations](#11-base-de-données-et-migrations)
12. [Tests](#12-tests)
13. [Installation et lancement](#13-installation-et-lancement)
14. [Arborescence du projet](#14-arborescence-du-projet)
15. [Conformité Factur-X / EN16931](#15-conformité-factur-x--en16931)
16. [Conventions de code](#16-conventions-de-code)

---

## 1. Contexte et objectif

### Qu'est-ce que Factur-X ?

Factur-X (aussi appelé ZUGFeRD en Allemagne) est un standard franco-allemand de facturation électronique. Une facture Factur-X est un **PDF/A-3** contenant un **fichier XML structuré** (`factur-x.xml`) en pièce jointe. Cela permet :

- **Aux humains** : de lire la facture comme un PDF classique
- **Aux machines** : d'extraire automatiquement les données depuis le XML embarqué

Le profil **EN16931** (aussi appelé COMFORT) est le profil de conformité européen qui définit les champs obligatoires, les règles de calcul (BR-CO), et les codes standards (ISO, UNTDID) à respecter.

### Objectif de l'application

FacturxV2 offre une **interface web complète** pour :
1. Créer et gérer des clients (fournisseur / acheteur)
2. Saisir des factures avec lignes, TVA multi-taux, remises et charges
3. Générer un PDF conforme avec XML EN16931 embarqué
4. Exposer une **API REST** pour parser des PDF existants et y embarquer du XML Factur-X

---

## 2. Stack technique

| Couche | Technologie | Rôle |
|--------|-------------|------|
| **Backend** | PHP 8.1+, Symfony 6.4 | Framework MVC, routing, validation, injection de dépendances |
| **ORM** | Doctrine ORM | Mapping objet-relationnel, migrations |
| **Base de données** | MySQL / MariaDB (Docker) | Persistance des factures, clients, lignes |
| **PDF** | Dompdf 3.1 | Rendu HTML vers PDF |
| **Factur-X** | atgp/factur-x 2.4 | Écriture du XML dans le PDF (Writer) |
| **PDF Parsing** | smalot/pdfparser 2.12 | Extraction de texte depuis un PDF existant (API) |
| **Frontend** | Twig + Tailwind CSS | Templates, interface utilisateur |
| **JS** | Stimulus (Symfony UX) + Turbo | Interactions dynamiques (formulaires, calculs) |
| **Assets** | AssetMapper (importmap) | Pas de Webpack, résolution native des modules ES |
| **Infra** | Docker Compose | MySQL + Mailpit (dev) |
| **Tests** | PHPUnit | Tests unitaires des services |

### Dépendances clés (composer.json)

```
symfony/framework-bundle    6.4.*     # Noyau Symfony
doctrine/doctrine-bundle    ^2.13     # ORM et migrations
dompdf/dompdf               ^3.1      # Rendu PDF depuis HTML
atgp/factur-x               ^2.4      # Embedding XML Factur-X dans PDF
smalot/pdfparser            ^2.12     # Extraction texte PDF
setasign/fpdi               ^2.6      # Manipulation PDF bas niveau
symfony/stimulus-bundle      ^2.22    # Stimulus JS
symfony/ux-turbo            ^2.22     # Turbo (navigation SPA-like)
```

---

## 3. Architecture globale

Le flux principal de l'application suit ce chemin :

```
Navigateur                  Symfony                         Services
    |                          |                               |
    |-- GET/POST ------------->|                               |
    |                   FactureController                      |
    |                     |        |                           |
    |                     |   FactureType                      |
    |                     |   (validation)                     |
    |                     |        |                           |
    |                     |   Doctrine ORM                     |
    |                     |   (persist/flush)                  |
    |                     |        |                           |
    |                     |-- download ----------------------->|
    |                     |                          FacturxService
    |                     |                            |           |
    |                     |                    buildXml()    embedXmlInExistingPdf()
    |                     |                    (DOMDocument)  (Dompdf + Writer)
    |                     |                            |           |
    |                     |                     factur-x.xml    PDF/A-3
    |                     |                            |___________|
    |                     |                                  |
    |<-- PDF Response ----|<---------------------------------|
```

### Séparation des responsabilités

| Couche | Fichiers | Responsabilité |
|--------|----------|---------------|
| **Controller** | `src/Controller/` | Routing HTTP, orchestration |
| **Entity** | `src/Entity/` | Modèle de données (Doctrine) |
| **Form** | `src/Form/` | Validation et hydratation des formulaires |
| **Repository** | `src/Repository/` | Requêtes Doctrine personnalisées |
| **Service** | `src/Service/` | Logique métier : construction XML, génération PDF, parsing |
| **Template** | `templates/` | Rendu HTML (Twig) |

---

## 4. Modèle de données (Entités)

Toutes les entités utilisent les **attributs PHP 8** (`#[ORM\Entity]`, `#[ORM\Column]`, etc.).

### 4.1 `Facture` — La facture

L'entité centrale du système. Représente une facture complète.

| Champ | Type | Description |
|-------|------|-------------|
| `id` | int (auto) | Clé primaire |
| `numero_facture` | string | Numéro unique (ex : `F2025-001`) |
| `date_facture` | Date | Date d'émission |
| `type_facture` | string | `FA` (facture), `FC` (avoir), `FN` (auto-facture) |
| `devise` | string | Code ISO 4217 (`EUR`, `USD`…) |
| `net_apayer` | decimal | Montant net à payer |
| `date_echeance` | Date | Date d'échéance de paiement |
| `date_livraison` | Date | Date de livraison |
| `mode_paiement` | string | Code UNTDID 4461 |
| `reference_paiement` | string | Référence de paiement |
| `commentaire` | text | Note libre |
| `charges` | decimal | Frais supplémentaires |
| `commande_acheteur` | string | Référence bon de commande |

**Relations :**
- `fournisseur` → `Client` (ManyToOne) : le vendeur
- `acheteur` → `Client` (ManyToOne) : l'acheteur
- `lignes` → `FactureLigne[]` (OneToMany, cascade) : les lignes de facture
- `allowanceCharges` → `FactureAllowanceCharge[]` (OneToMany, cascade) : remises/charges globales
- `paymentMeans` → `PaymentMeans[]` (OneToMany, cascade) : moyens de paiement

**Méthodes de calcul :**
- `getTotalHt()` : somme des `montant_ht` de toutes les lignes
- `getTotalTva()` : somme des `montant_tva` de toutes les lignes
- `getTotalTtc()` : `totalHt + totalTva`
- `getTaxBasisTotal()` : `totalHt + charges - remises`

### 4.2 `FactureLigne` — Ligne de facture

Représente un produit ou service facturé.

| Champ | Type | Description |
|-------|------|-------------|
| `designation` | string | Nom du produit/service |
| `reference` | string | Référence article |
| `quantite` | decimal | Quantité facturée |
| `unite` | string | Unité de mesure (code UN/ECE Rec 20) |
| `prix_unitaire_ht` | decimal | Prix unitaire HT |
| `taux_tva` | decimal | Taux de TVA (ex : 20.00) |
| `montant_ht` | decimal | `quantité × prix_unitaire_ht` |
| `montant_tva` | decimal | `montant_ht × taux_tva / 100` |
| `montant_ttc` | decimal | `montant_ht + montant_tva` |

### 4.3 `Client` — Fournisseur ou acheteur

Un même client peut être fournisseur sur une facture et acheteur sur une autre.

| Champ | Type | Description |
|-------|------|-------------|
| `nom` | string | Raison sociale |
| `siren` | string | Numéro SIREN (9 chiffres) |
| `siret` | string | Numéro SIRET (14 chiffres) |
| `tva` / `numero_tva` | string | N° TVA intracommunautaire |
| `email` | string | Email |
| `adresse`, `ville`, `code_postal` | string | Adresse postale |
| `code_pays` | string | Code ISO 3166-1 alpha-2 (`FR`, `DE`…) |
| `telephone` | string | Téléphone |

### 4.4 `FactureAllowanceCharge` — Remise ou charge globale

| Champ | Type | Description |
|-------|------|-------------|
| `amount` | decimal | Montant |
| `tax_rate` | decimal | Taux TVA associé |
| `is_charge` | bool | `true` = charge, `false` = remise |
| `reason` | string | Motif (obligatoire selon BR-33/BR-37) |

### 4.5 `PaymentMeans` — Moyen de paiement

| Champ | Type | Description |
|-------|------|-------------|
| `code` | string | Code UNTDID 4461 (ex : `42` = virement) |
| `information` | string | Détails (IBAN, BIC…) |

### Diagramme des relations

```
Client (fournisseur)
   |
   | ManyToOne
   v
Facture ----< OneToMany >---- FactureLigne
   |                              (lignes)
   |
   |----< OneToMany >---- FactureAllowanceCharge
   |                          (remises/charges)
   |
   |----< OneToMany >---- PaymentMeans
   |                        (moyens de paiement)
   |
   | ManyToOne
   v
Client (acheteur)
```

---

## 5. Contrôleurs et routes

### 5.1 `FactureController` — Interface web

Gère tout le CRUD des factures et la génération PDF.

| Méthode | Route | Action |
|---------|-------|--------|
| `index()` | `GET /` | Liste toutes les factures |
| `new()` | `GET/POST /new` | Formulaire de création (HTML pur, pas Symfony Form) |
| `show()` | `GET /{id}` | Affiche une facture (rendu template PDF) |
| `edit()` | `GET/POST /{id}/edit` | Édition via FactureType (Symfony Form) |
| `delete()` | `POST /{id}/delete` | Suppression (protection CSRF) |
| `downloadFacturx()` | `GET /{id}/download` | Génération et téléchargement du PDF Factur-X |

**Flux de `downloadFacturx()` :**
1. Charge la facture depuis Doctrine
2. Rend le template `pdfA3.html.twig` en HTML
3. Dompdf convertit le HTML en PDF binaire
4. `FacturxService::embedXmlInExistingPdf()` génère le XML et l'embarque dans le PDF
5. Retourne le PDF au navigateur en `application/pdf`

### 5.2 `ApiFacturxController` — API REST

Endpoints pour l'intégration programmatique.

| Méthode | Route | Action |
|---------|-------|--------|
| `POST` | `/api/facturx/parse` | Upload un PDF, extrait le texte, retourne un JSON pré-rempli |
| `GET` | `/api/facturx/ui` | Interface web pour tester l'API |
| `POST` | `/api/facturx/embed` | Reçoit un PDF + JSON, retourne le PDF avec XML Factur-X embarqué |
| `POST` | `/api/facturx/xml` | Reçoit un JSON, retourne le fichier XML Factur-X |

---

## 6. Services métier

### 6.1 `FacturxService` — Cœur de la génération Factur-X

C'est le service le plus important de l'application. Il est responsable de :

#### `buildXml(Facture $facture): string`

Construit le XML EN16931 complet avec `DOMDocument`. Structure générée :

```xml
<rsm:CrossIndustryInvoice>
  <rsm:ExchangedDocumentContext>
    <!-- Profil EN16931 -->
  </rsm:ExchangedDocumentContext>
  <rsm:ExchangedDocument>
    <!-- Numéro, date, type de facture -->
  </rsm:ExchangedDocument>
  <rsm:SupplyChainTradeTransaction>
    <ram:IncludedSupplyChainTradeLineItem>
      <!-- Lignes : désignation, prix, quantité, TVA -->
    </ram:IncludedSupplyChainTradeLineItem>
    <ram:ApplicableHeaderTradeAgreement>
      <!-- Vendeur (SIRET, TVA, SIREN, adresse) -->
      <!-- Acheteur (nom, adresse, pays) -->
    </ram:ApplicableHeaderTradeAgreement>
    <ram:ApplicableHeaderTradeDelivery>
      <!-- Date de livraison -->
    </ram:ApplicableHeaderTradeDelivery>
    <ram:ApplicableHeaderTradeSettlement>
      <!-- Moyens de paiement (IBAN/BIC) -->
      <!-- Remises et charges globales -->
      <!-- Blocs TVA groupés par taux -->
      <!-- Totaux monétaires (ordre XSD strict) -->
    </ram:ApplicableHeaderTradeSettlement>
  </rsm:SupplyChainTradeTransaction>
</rsm:CrossIndustryInvoice>
```

Points importants :
- Les **identifiants légaux** utilisent les bons `schemeID` : `0009` (SIRET), `VA` (TVA), `FC` (SIREN)
- La **TVA est groupée par taux** : un bloc `ApplicableTradeTax` par taux distinct
- Les **totaux suivent l'ordre XSD** : LineTotalAmount → ChargeTotalAmount → AllowanceTotalAmount → TaxBasisTotalAmount → TaxTotalAmount → GrandTotalAmount → DuePayableAmount
- Le XML est sauvegardé dans `public/factures/xml/facture_{numéro}_fx.xml`

#### `embedXmlInExistingPdf(string $pdfContent, Facture $facture): string`

1. Appelle `buildXml()` pour générer le XML
2. Utilise `Atgp\FacturX\Writer` pour embarquer le XML dans le PDF
3. Profil : `PROFILE_FACTURX_EN16931`
4. Retourne le contenu binaire du PDF final

### 6.2 `FactureBuilderService` — Construction depuis JSON/PDF

#### `buildFromArray(array $data): Facture`

Construit une entité `Facture` complète à partir d'un tableau PHP (typiquement issu d'un JSON API). Crée en mémoire (sans persistance Doctrine) les objets `Client`, `FactureLigne`, `FactureAllowanceCharge`, et `PaymentMeans`. Valide les champs requis et calcule automatiquement les montants HT/TVA/TTC de chaque ligne.

#### `extractFromPdfText(string $rawText): array`

Extrait les données de facture depuis du texte brut PDF via des expressions régulières. Détecte : numéro de facture, date, noms et adresses des parties, SIRET, TVA, IBAN/BIC. Retourne un tableau compatible avec `buildFromArray()`.

---

## 7. Formulaires Symfony

### `FactureType`

Formulaire pour l'édition de factures. Champs :
- `numeroFacture`, `dateFacture`, `dateEcheance`
- `fournisseur` et `acheteur` (EntityType → Client)
- `lignes` (CollectionType → FactureLigneType, ajout/suppression dynamique)

### `FactureLigneType`

Sous-formulaire pour chaque ligne :
- `designation`, `reference`, `quantite`, `unite`
- `prixUnitaireHT` (MoneyType)
- `tauxTVA`
- `montantHT`, `montantTVA`, `montantTTC` (MoneyType, lecture seule — calculés côté client)

---

## 8. Templates Twig

| Template | Rôle |
|----------|------|
| `base.html.twig` | Layout principal : navbar (Tailwind CSS + Feather icons), footer |
| `facture/index.html.twig` | Tableau des factures avec lignes dépliables, actions (voir, télécharger, éditer, supprimer) |
| `facture/new.html.twig` | Formulaire HTML de création avec JS dynamique pour lignes, remises, paiements |
| `facture/edit.html.twig` | Édition avec Symfony Form + JS pour recalcul temps réel des totaux |
| `facture/pdfA3.html.twig` | Template HTML pour le rendu PDF via Dompdf (style inline, police Poppins, format français) |
| `api/embed.html.twig` | Interface web de l'API : upload PDF → pré-remplissage → génération Factur-X |

### Template PDF (`pdfA3.html.twig`)

Ce template est converti en PDF par Dompdf. Il contient :
- En-tête avec coordonnées vendeur/acheteur
- Informations de la facture (numéro, date, échéance)
- Tableau des lignes (désignation, quantité, prix unitaire, TVA, total)
- Section remises/charges globales
- Totaux (HT, TVA, TTC, net à payer)
- Informations de paiement
- Notes/commentaires

---

## 9. API REST

L'API permet d'intégrer la génération Factur-X dans des workflows externes.

### `POST /api/facturx/parse`

Upload un PDF, extrait le texte et retourne un JSON pré-rempli.

**Requête :** `multipart/form-data` avec champ `pdf` (fichier PDF)
**Réponse :** JSON avec les champs détectés

### `POST /api/facturx/embed`

Reçoit un PDF existant et des données de facture en JSON, retourne le PDF avec XML Factur-X embarqué.

**Requête :** `multipart/form-data` avec `pdf` (fichier) + `data` (JSON)
**Réponse :** `application/pdf`

### `POST /api/facturx/xml`

Reçoit des données JSON et retourne le XML Factur-X généré.

**Requête :** `application/json`
**Réponse :** `application/xml`

### Structure JSON attendue

```json
{
  "numeroFacture": "F2025-001",
  "dateFacture": "2025-10-07",
  "typeFacture": "FA",
  "devise": "EUR",
  "fournisseur": {
    "nom": "Oroya SARL",
    "siret": "12345678901234",
    "siren": "123456789",
    "numeroTva": "FR12345678901",
    "adresse": "10 rue de Paris",
    "ville": "Paris",
    "codePostal": "75001",
    "codePays": "FR",
    "email": "contact@oroya.fr"
  },
  "acheteur": {
    "nom": "Client SAS",
    "adresse": "20 avenue des Champs",
    "ville": "Lyon",
    "codePostal": "69001",
    "codePays": "FR"
  },
  "lignes": [
    {
      "designation": "Licence logicielle",
      "reference": "LIC-001",
      "quantite": 2,
      "unite": "H87",
      "prixUnitaireHt": 500.00,
      "tauxTva": 20.00
    }
  ],
  "allowanceCharges": [
    {
      "amount": 50.00,
      "taxRate": 20.00,
      "isCharge": false,
      "reason": "Remise fidélité"
    }
  ],
  "paymentMeans": [
    {
      "code": "42",
      "information": "FR7630001007941234567890185"
    }
  ]
}
```

---

## 10. Frontend (Stimulus / Turbo)

L'application utilise **Symfony UX** avec :

- **Stimulus** : contrôleurs JS pour les interactions dynamiques (ajout/suppression de lignes, calcul temps réel des totaux, toggle création/sélection de clients)
- **Turbo** : navigation SPA-like sans rechargement complet de page
- **AssetMapper** : gestion des assets via `importmap.php` (pas de Webpack/Encore)
- **Tailwind CSS** : classes utilitaires pour le style
- **Feather Icons** : icônes SVG dans la navbar

Les modules JS sont déclarés dans `importmap.php` :
```php
'@hotwired/stimulus' => '3.2.2'
'@hotwired/turbo'    => '7.3.0'
'@symfony/stimulus-bundle'
```

---

## 11. Base de données et migrations

### Docker Compose

```yaml
services:
  database:
    image: mysql
    ports: ["3306:3306"]
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: facturx
  mailpit:
    image: axllent/mailpit
    ports: ["1025:1025", "8025:8025"]
```

### Schéma de la base (5 tables)

| Table | Description |
|-------|-------------|
| `client` | Fournisseurs et acheteurs |
| `facture` | Factures avec FK vers client (fournisseur + acheteur) |
| `facture_ligne` | Lignes de facture (FK → facture) |
| `facture_allowance_charge` | Remises et charges globales (FK → facture) |
| `payment_means` | Moyens de paiement (FK → facture) |

### Migrations

- `Version20251007085706` : Création complète du schéma (5 tables + FK)
- `Version20251007092055` : Ajout de `facture.commande_acheteur`, suppression de `profil_factur_x` (calculé à la volée)

---

## 12. Tests

Les tests sont dans `tests/Service/` et couvrent les deux services principaux.

### `FacturxServiceTest`

- Crée une facture fixture avec 2 lignes (500 EUR @ 20% + 200 EUR @ 10%)
- Teste `buildXml()` : vérifie que le XML est généré et sauvegardé
- Teste `embedXmlInExistingPdf()` : vérifie l'embedding dans un PDF

### `FactureBuilderServiceTest`

- Teste `buildFromArray()` : construction d'une entité depuis un tableau
- Vérifie les calculs automatiques (montantHT, montantTVA, montantTTC)
- Teste `extractFromPdfText()` : extraction de données depuis du texte PDF

### Lancer les tests

```bash
php bin/phpunit                              # Tous les tests
php bin/phpunit tests/Service/               # Tests des services uniquement
php bin/phpunit tests/Service/FacturxServiceTest.php  # Un fichier spécifique
```

---

## 13. Installation et lancement

### Prérequis

- PHP 8.1+
- Composer
- Docker & Docker Compose (pour MySQL)
- Symfony CLI (optionnel, pour `symfony server:start`)

### Étapes

```bash
# 1. Cloner le projet
git clone https://github.com/BBgamesTV/FacturxV2.git
cd FacturxV2

# 2. Installer les dépendances PHP
composer install

# 3. Démarrer les conteneurs Docker (MySQL + Mailpit)
docker compose up -d

# 4. Créer la base de données
php bin/console doctrine:database:create

# 5. Exécuter les migrations
php bin/console doctrine:migrations:migrate

# 6. (Optionnel) Charger les données de test
php bin/console doctrine:fixtures:load

# 7. Lancer le serveur de développement
symfony server:start
```

### Accès

| URL | Description |
|-----|-------------|
| `http://localhost:8000` | Application principale |
| `http://localhost:8000/api/facturx/ui` | Interface API Factur-X |
| `http://localhost:8025` | Mailpit (emails de dev) |

### Variables d'environnement (`.env`)

```dotenv
APP_ENV=dev
DATABASE_URL="mysql://root:root@127.0.0.1:3306/facturx"
MAILER_DSN=smtp://localhost:1025
```

---

## 14. Arborescence du projet

```
FacturxV2/
│
├── src/
│   ├── Controller/
│   │   ├── FactureController.php       # CRUD factures + génération PDF
│   │   └── ApiFacturxController.php    # API REST (parse, embed, xml)
│   │
│   ├── Entity/
│   │   ├── Facture.php                 # Entité facture (relations, calculs)
│   │   ├── FactureLigne.php            # Ligne de facture
│   │   ├── Client.php                  # Fournisseur / acheteur
│   │   ├── FactureAllowanceCharge.php  # Remise ou charge globale
│   │   └── PaymentMeans.php            # Moyen de paiement
│   │
│   ├── Form/
│   │   ├── FactureType.php             # Formulaire facture (édition)
│   │   └── FactureLigneType.php        # Sous-formulaire ligne
│   │
│   ├── Repository/
│   │   ├── FactureRepository.php       # Requêtes : findByFournisseur, findOverdue…
│   │   └── ClientRepository.php        # Repository client (standard)
│   │
│   ├── Service/
│   │   ├── FacturxService.php          # Construction XML + embedding PDF
│   │   └── FactureBuilderService.php   # Construction entité depuis JSON/PDF
│   │
│   └── DataFixtures/
│       └── FactureFixtures.php         # Données de test (1 facture, 2 lignes)
│
├── templates/
│   ├── base.html.twig                  # Layout (navbar, Tailwind, footer)
│   ├── facture/
│   │   ├── index.html.twig             # Liste des factures
│   │   ├── new.html.twig               # Création (formulaire HTML)
│   │   ├── edit.html.twig              # Édition (Symfony Form)
│   │   └── pdfA3.html.twig             # Template pour rendu PDF
│   └── api/
│       └── embed.html.twig             # Interface web API
│
├── config/
│   ├── services.yaml                   # Injection $projectDir dans FacturxService
│   └── packages/
│       ├── doctrine.yaml               # Connexion MySQL, mapping attributs
│       ├── twig.yaml
│       ├── security.yaml
│       └── framework.yaml
│
├── migrations/                         # Migrations Doctrine
├── tests/Service/                      # Tests PHPUnit
├── assets/                             # JS (Stimulus) + CSS
├── public/
│   ├── index.php                       # Point d'entrée Symfony
│   ├── fonts/Poppins-Regular.ttf       # Police pour le PDF
│   └── factures/xml/                   # XMLs générés (sortie)
│
├── compose.yaml                        # Docker : MySQL + Mailpit
├── composer.json                       # Dépendances PHP
├── importmap.php                       # Modules JS (Stimulus, Turbo)
├── CLAUDE.md                           # Instructions pour Claude Code
└── FACTURX_CRITERES.md                 # Référence complète norme EN16931
```

---

## 15. Conformité Factur-X / EN16931

L'application génère des factures conformes au **profil EN16931** de la norme Factur-X. Voici ce qui est implémenté :

### Éléments gérés

| Critère | Statut | Détail |
|---------|--------|--------|
| Structure XML (namespaces, racine) | Implémenté | `buildXml()` |
| Numéro, date, type de facture | Implémenté | BT-1, BT-2, BT-3 |
| Vendeur : nom, adresse, pays | Implémenté | BT-27, BT-35…40 |
| Vendeur : SIRET (schemeID=0009) | Implémenté | BT-30 |
| Vendeur : TVA (schemeID=VA) | Implémenté | BT-31 |
| Vendeur : SIREN (schemeID=FC) | Implémenté | BT-32 |
| Acheteur : nom, adresse, pays | Implémenté | BT-44, BT-50…55 |
| Lignes : désignation, prix, quantité, TVA | Implémenté | BT-126…153 |
| TVA groupée par taux | Implémenté | BG-23 (blocs ApplicableTradeTax) |
| Totaux monétaires (ordre XSD strict) | Implémenté | BT-106…115 |
| Remises/charges globales | Implémenté | BG-20, BG-21 |
| Moyens de paiement (IBAN/BIC SEPA) | Implémenté | BT-81…86 |
| Date de livraison | Implémenté | BT-72 |
| Embedding XML dans PDF/A-3 | Implémenté | `Writer::generate()` |

### Codes standards utilisés

- **Pays** : ISO 3166-1 alpha-2 (`FR`, `DE`, `US`…)
- **Devises** : ISO 4217 (`EUR`, `USD`…)
- **Types de document** : UNTDID 1001 (`380` = facture, `381` = avoir)
- **Moyens de paiement** : UNTDID 4461 (`42` = virement, `48` = carte…)
- **Catégories TVA** : UNTDID 5305 (`S` = standard, `Z` = taux zéro, `E` = exempté…)
- **Unités de mesure** : UN/ECE Rec 20 (`H87` = pièce, `HUR` = heure…)
- **Identifiants légaux** : ISO 6523 (`0009` = SIRET, `VA` = TVA, `FC` = SIREN)

> Pour la référence complète des critères EN16931, voir [`FACTURX_CRITERES.md`](./FACTURX_CRITERES.md).

---

## 16. Conventions de code

- **Attributs PHP 8** pour Doctrine (`#[ORM\Entity]`), routing (`#[Route]`) et validation (`#[Assert\NotBlank]`) — pas d'annotations DocBlock
- **Logique métier dans `src/Service/`** — les contrôleurs restent fins (orchestration uniquement)
- **Validation ISO** sur les entités via contraintes Symfony (pays, devises, codes UNTDID)
- **Frontend Symfony UX** : Stimulus + Turbo avec AssetMapper (pas de Webpack Encore)
- **Conformité EN16931** obligatoire pour toute fonctionnalité de facturation

---

## Contribuer

1. Fork du repository
2. Créer une branche (`feature/ma-fonction`)
3. Proposer un pull request

---

## Auteurs & liens

- Code par [BBgamesTV](https://github.com/BBgamesTV)
- Bibliothèque Factur-X par [atgp/factur-x](https://github.com/atgp/factur-x)

## Licence

Projet open-source sous licence MIT.

---

> Pour toute question technique ou demande de support, crée une issue GitHub sur ce repository.
