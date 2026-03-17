# Critères de validité Factur-X — Profil EN16931 (COMFORT)

> **Référence** : Spécification Factur-X v1.0.06 / EN16931-1:2017 + corr.
> **Profil ciblé** : `urn:cen.eu:en16931:2017` (= COMFORT / EN16931)
> **Légende** : ✅ Obligatoire · ⚠️ Obligatoire si la section est présente · 🔵 Recommandé · ⬜ Optionnel

---

## 1. STRUCTURE XML (toujours obligatoire)

| # | Élément CII | Statut | Valeur attendue |
|---|------------|--------|-----------------|
| — | Namespace `rsm:` | ✅ | `urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100` |
| — | Namespace `ram:` | ✅ | `urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100` |
| — | Namespace `udt:` | ✅ | `urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100` |
| — | Namespace `qdt:` | ✅ | `urn:un:unece:uncefact:data:standard:QualifiedDataType:100` |
| — | **ExchangedDocumentContext** | ✅ | Bloc racine du contexte |
| BT-24 | `GuidelineSpecifiedDocumentContextParameter/ID` | ✅ | `urn:cen.eu:en16931:2017` |
| — | **ExchangedDocument** | ✅ | En-tête du document |
| — | **SupplyChainTradeTransaction** | ✅ | Corps de la transaction |

---

## 2. EN-TÊTE DU DOCUMENT (ExchangedDocument)

| BT | Champ | Statut | Notes |
|----|-------|--------|-------|
| BT-1 | `ram:ID` — Numéro de facture | ✅ | Unique, séquentiel, non modifiable |
| BT-3 | `ram:TypeCode` — Type de document | ✅ | **380** = Facture · **381** = Avoir · **389** = Auto-facture · **751** = Relevé · [UNTDID 1001] |
| BT-2 | `ram:IssueDateTime/udt:DateTimeString` | ✅ | Format **102** = AAAAMMJJ |
| BT-22 | `ram:IncludedNote/ram:Content` | ⬜ | Commentaire libre |
| BT-21 | `ram:IncludedNote/ram:SubjectCode` | ⬜ | Code sujet note [UNTDID 4451] |

---

## 3. VENDEUR / FOURNISSEUR (SellerTradeParty)

> Bloc : `ApplicableHeaderTradeAgreement/SellerTradeParty`

| BT | Champ | Statut | Notes |
|----|-------|--------|-------|
| BT-27 | `ram:Name` | ✅ | Raison sociale |
| BT-28 | `ram:TradingBusinessName` | ⬜ | Nom commercial si différent |
| BT-29 | `ram:ID` (schemeID optionnel) | ⬜ | Identifiant vendeur interne |
| BT-30 | `SpecifiedLegalOrganization/ram:ID` | ⬜ | **SIRET** (schemeID=`0009`) — satisfait BR-CO-26 |
| BT-31 | `SpecifiedTaxRegistration/ram:ID` (schemeID=`VA`) | ✅* | N° TVA intracommunautaire — **requis si assujetti** |
| BT-32 | `SpecifiedTaxRegistration/ram:ID` (schemeID=`FC`) | ⬜ | SIREN — satisfait BR-S-02 si pas de TVA |
| BT-33 | `ram:Description` dans LegalOrg | ⬜ | Forme juridique (SARL, SAS…) |
| BT-34 | `ram:URIUniversalCommunication/ram:URIID` | ⬜ | Email vendeur (schemeID=`EM`) |

### 3.1 Adresse postale vendeur (PostalTradeAddress)

| BT | Champ | Statut | Notes |
|----|-------|--------|-------|
| BT-35 | `ram:LineOne` | 🔵 | Ligne adresse 1 |
| BT-36 | `ram:LineTwo` | ⬜ | Ligne adresse 2 |
| BT-37 | `ram:LineThree` | ⬜ | Ligne adresse 3 |
| BT-38 | `ram:CityName` | 🔵 | Ville |
| BT-39 | `ram:PostcodeCode` | 🔵 | Code postal |
| BT-40 | `ram:CountryID` | ✅ | **ISO 3166-1 alpha-2** (ex: `FR`) — BR-8 |
| BT-40 | `ram:CountrySubDivisionName` | ⬜ | Région / département |

### 3.2 Contact vendeur (DefinedTradeContact)

| BT | Champ | Statut | Notes |
|----|-------|--------|-------|
| BT-41 | `ram:PersonName` | ⬜ | Nom du contact |
| BT-41-0 | `ram:DepartmentName` | ⬜ | Service / département |
| BT-42 | `TelephoneUniversalCommunication/ram:CompleteNumber` | ⬜ | Téléphone |
| BT-43 | `EmailURIUniversalCommunication/ram:URIID` | ⬜ | Email contact |

---

## 4. ACHETEUR / CLIENT (BuyerTradeParty)

> Bloc : `ApplicableHeaderTradeAgreement/BuyerTradeParty`

| BT | Champ | Statut | Notes |
|----|-------|--------|-------|
| BT-44 | `ram:Name` | ✅ | Raison sociale — BR-7 |
| BT-45 | `ram:TradingBusinessName` | ⬜ | Nom commercial |
| BT-46 | `ram:ID` | ⬜ | Identifiant acheteur |
| BT-47 | `SpecifiedLegalOrganization/ram:ID` | ⬜ | SIRET acheteur |
| BT-48 | `SpecifiedTaxRegistration/ram:ID` (schemeID=`VA`) | ⬜ | N° TVA acheteur |
| BT-49 | `ram:URIUniversalCommunication/ram:URIID` | ⬜ | Email acheteur |
| BT-10 | `ram:BuyerReference` | ⬜* | **Requis pour PEPPOL BIS** (pas EN16931 pur) |

### 4.1 Adresse postale acheteur

| BT | Champ | Statut | Notes |
|----|-------|--------|-------|
| BT-50 | `ram:LineOne` | 🔵 | Ligne adresse 1 |
| BT-51 | `ram:LineTwo` | ⬜ | Ligne adresse 2 |
| BT-52 | `ram:LineThree` | ⬜ | Ligne adresse 3 |
| BT-53 | `ram:CityName` | 🔵 | Ville |
| BT-54 | `ram:PostcodeCode` | 🔵 | Code postal |
| BT-55 | `ram:CountryID` | ✅ | ISO 3166-1 alpha-2 — BR-9 |

---

## 5. RÉFÉRENCES DOCUMENTAIRES (dans ApplicableHeaderTradeAgreement)

| BT | Champ CII | Statut | Notes |
|----|-----------|--------|-------|
| BT-13 | `BuyerOrderReferencedDocument/ram:IssuerAssignedID` | ⬜ | N° bon de commande acheteur |
| BT-14 | `SellerOrderReferencedDocument/ram:IssuerAssignedID` | ⬜ | N° commande vendeur |
| BT-12 | `ContractReferencedDocument/ram:IssuerAssignedID` | ⬜ | N° contrat |
| BT-11 | `SpecifiedProcuringProject/ram:ID` | ⬜ | N° projet |

---

## 6. LIVRAISON (ApplicableHeaderTradeDelivery)

| BT | Champ CII | Statut | Notes |
|----|-----------|--------|-------|
| BT-72 | `ActualDeliverySupplyChainEvent/OccurrenceDateTime` | ⬜ | Date de livraison réelle (format 102) |
| BT-70 | `ShipToTradeParty/ram:Name` | ⬜ | Destinataire si différent acheteur |
| BT-71 | `ShipToTradeParty/ram:ID` | ⬜ | Identifiant lieu de livraison |
| BT-73 | `BillingSpecifiedPeriod/ram:StartDateTime` | ⬜ | Début période de facturation |
| BT-74 | `BillingSpecifiedPeriod/ram:EndDateTime` | ⬜ | Fin période de facturation |

---

## 7. MOYENS DE PAIEMENT (SpecifiedTradeSettlementPaymentMeans)

| BT | Champ CII | Statut | Notes |
|----|-----------|--------|-------|
| BT-81 | `ram:TypeCode` | ✅ | Code UNTDID 4461 (ex: `42`=virement, `30`=chèque, `48`=carte, `59`=prélèvement SEPA) |
| BT-82 | `ram:Information` | ⬜ | Texte libre décrivant le moyen |
| BT-83 | `ram:PaymentReference` | ⬜ | Référence de remise (dans PaymentTerms) |

### 7.1 Virement bancaire (si TypeCode = 42 ou 58)

| BT | Champ CII | Statut | Notes |
|----|-----------|--------|-------|
| BT-84 | `PayeePartyCreditorFinancialAccount/ram:IBANID` | ✅ | IBAN — obligatoire pour virement |
| BT-85 | `PayeePartyCreditorFinancialAccount/ram:AccountName` | ⬜ | Nom titulaire compte |
| BT-86 | `PayeeSpecifiedCreditorFinancialInstitution/ram:BICID` | ⬜ | BIC (recommandé) |

### 7.2 Prélèvement SEPA (si TypeCode = 59)

| BT | Champ CII | Statut | Notes |
|----|-----------|--------|-------|
| BT-89 | `ram:ID` dans PaymentMeans | ✅ | Référence mandat |
| BT-90 | `CreditorFinancialAccount/ram:ProprietaryID` | ✅ | ICS (Identifiant Créancier SEPA) |
| BT-91 | `DebtorPartyDebtorFinancialAccount/ram:IBANID` | ⬜ | IBAN débiteur |

### 7.3 Carte bancaire (si TypeCode = 48)

| BT | Champ CII | Statut | Notes |
|----|-----------|--------|-------|
| BT-87 | `ApplicableTradeSettlementFinancialCard/ram:ID` | ✅ | 4 derniers chiffres de la carte |
| BT-88 | `ApplicableTradeSettlementFinancialCard/ram:CardholderName` | ⬜ | Nom porteur |

---

## 8. CONDITIONS DE PAIEMENT (SpecifiedTradePaymentTerms)

| BT | Champ CII | Statut | Notes |
|----|-----------|--------|-------|
| BT-20 | `ram:Description` | ⬜ | Texte libre conditions de paiement |
| BT-9  | `ram:DueDateDateTime/udt:DateTimeString` | ⬜ | Date d'échéance (format 102) |

> ⚠️ **BR-CO-25** : `DuePayableAmount` (BT-115) doit être cohérent avec la date d'échéance.

---

## 9. TAXES GLOBALES (ApplicableTradeTax — niveau document)

> Un bloc par taux de TVA distinct — BR-11 impose au moins un.

| BT | Champ CII | Statut | Notes |
|----|-----------|--------|-------|
| BT-116 | `ram:BasisAmount` | ✅ | Montant HT taxable pour ce taux |
| BT-117 | `ram:CalculatedAmount` | ✅ | Montant TVA calculé |
| BT-118 | `ram:CategoryCode` | ✅ | **S**=standard · **Z**=taux zéro · **E**=exempté · **AE**=autoliquidation · **K**=intracom · **G**=export · **O**=hors TVA · [UNTDID 5305] |
| BT-119 | `ram:RateApplicablePercent` | ⚠️ | Obligatoire si CategoryCode=**S** ou **Z** |
| BT-120 | `ram:ExemptionReason` | ⚠️ | Obligatoire si CategoryCode=**E**, **AE**, **K**, **G**, **O** |
| BT-121 | `ram:ExemptionReasonCode` | ⬜ | Code motif d'exemption [VATEX] |
| — | `ram:TypeCode` | ✅ | Toujours `VAT` |

---

## 10. REMISES / CHARGES GLOBALES (niveau document)

### 10.1 Remises (SpecifiedTradeAllowanceCharge — isCharge=false)

| BT | Champ CII | Statut | Notes |
|----|-----------|--------|-------|
| BT-92 | `ram:ActualAmount` | ✅ | Montant de la remise |
| BT-93 | `ram:BasisAmount` | ⬜ | Base de calcul |
| BT-94 | `ram:CalculationPercent` | ⬜ | Taux de remise en % |
| BT-95 | `ram:CategoryTradeTax/ram:CategoryCode` | ✅ | Code TVA [UNTDID 5305] |
| BT-96 | `ram:CategoryTradeTax/ram:RateApplicablePercent` | ⚠️ | Requis si S ou Z |
| BT-97 | `ram:Reason` | ✅ | Motif de la remise — BR-33 |
| BT-98 | `ram:ReasonCode` | ⬜ | Code motif [UNTDID 5189] |

### 10.2 Charges (SpecifiedTradeAllowanceCharge — isCharge=true)

| BT | Champ CII | Statut | Notes |
|----|-----------|--------|-------|
| BT-99  | `ram:ActualAmount` | ✅ | Montant de la charge |
| BT-100 | `ram:BasisAmount` | ⬜ | Base de calcul |
| BT-101 | `ram:CalculationPercent` | ⬜ | Taux en % |
| BT-102 | `ram:CategoryTradeTax/ram:CategoryCode` | ✅ | Code TVA [UNTDID 5305] |
| BT-103 | `ram:CategoryTradeTax/ram:RateApplicablePercent` | ⚠️ | Requis si S ou Z |
| BT-104 | `ram:Reason` | ✅ | Motif — BR-37 |
| BT-105 | `ram:ReasonCode` | ⬜ | Code motif [UNTDID 7161] |

---

## 11. TOTAUX MONÉTAIRES (SpecifiedTradeSettlementHeaderMonetarySummation)

> **Ordre XSD strict à respecter.**

| BT | Champ CII | Statut | Formule |
|----|-----------|--------|---------|
| BT-106 | `ram:LineTotalAmount` | ✅ | Σ montants HT lignes |
| BT-108 | `ram:ChargeTotalAmount` | ⚠️ | Σ charges document (si présentes) |
| BT-107 | `ram:AllowanceTotalAmount` | ⚠️ | Σ remises document (si présentes) |
| BT-109 | `ram:TaxBasisTotalAmount` | ✅ | BT-106 + BT-108 − BT-107 |
| BT-110 | `ram:TaxTotalAmount` (currencyID requis) | ✅ | Σ TVA — attribut `@currencyID` obligatoire |
| BT-112 | `ram:GrandTotalAmount` | ✅ | BT-109 + BT-110 |
| BT-113 | `ram:TotalPrepaidAmount` | ⬜ | Acomptes déjà versés |
| BT-114 | `ram:RoundingAmount` | ⬜ | Arrondi |
| BT-115 | `ram:DuePayableAmount` | ✅ | BT-112 − BT-113 + BT-114 |

---

## 12. LIGNES DE FACTURE (IncludedSupplyChainTradeLineItem)

> Au moins une ligne obligatoire — BR-10.

### 12.1 Identifiant de ligne (AssociatedDocumentLineDocument)

| BT | Champ CII | Statut | Notes |
|----|-----------|--------|-------|
| BT-126 | `ram:LineID` | ✅ | Identifiant unique de ligne (1, 2, 3…) |
| BT-127 | `ram:IncludedNote/ram:Content` | ⬜ | Note sur la ligne |

### 12.2 Produit / Service (SpecifiedTradeProduct)

| BT | Champ CII | Statut | Notes |
|----|-----------|--------|-------|
| BT-153 | `ram:Name` | ✅ | Désignation article — BR-25 |
| BT-154 | `ram:Description` | ⬜ | Description détaillée |
| BT-155 | `ram:SellerAssignedID` | ⬜ | Référence vendeur |
| BT-156 | `ram:BuyerAssignedID` | ⬜ | Référence acheteur |
| BT-157 | `ram:GlobalID` (schemeID GTIN, etc.) | ⬜ | Code EAN/GTIN |
| BT-158 | `ram:DesignatedProductClassification/ram:ClassCode` | ⬜ | Classification CPV, UNSPSC… |
| BT-159 | `ram:OriginTradeCountry/ram:ID` | ⬜ | Pays d'origine (ISO 3166-1) |
| BT-160/161 | `ram:ApplicableProductCharacteristic` | ⬜ | Attributs produit (nom + valeur) |

### 12.3 Prix net (SpecifiedLineTradeAgreement/NetPriceProductTradePrice)

| BT | Champ CII | Statut | Notes |
|----|-----------|--------|-------|
| BT-146 | `ram:ChargeAmount` | ✅ | Prix unitaire net HT — BR-26 |
| BT-147 | `ram:AppliedTradeAllowanceCharge/ram:ActualAmount` | ⬜ | Remise sur prix brut |
| BT-148 | `ram:GrossPriceProductTradePrice/ram:ChargeAmount` | ⬜ | Prix brut avant remise |
| BT-149 | `ram:BasisQuantity` | ⬜ | Quantité de base pour le prix |
| BT-150 | `ram:BasisQuantity/@unitCode` | ⬜ | Unité quantité de base [UN/ECE Rec 20] |

### 12.4 Quantité (SpecifiedLineTradeDelivery)

| BT | Champ CII | Statut | Notes |
|----|-----------|--------|-------|
| BT-129 | `ram:BilledQuantity` | ✅ | Quantité facturée — BR-22 |
| BT-130 | `ram:BilledQuantity/@unitCode` | ✅ | Unité [UN/ECE Rec 20] — BR-23 (ex: `H87`=pièce, `HUR`=heure, `KGM`=kg, `MTR`=mètre) |

### 12.5 TVA de ligne (SpecifiedLineTradeSettlement/ApplicableTradeTax)

| BT | Champ CII | Statut | Notes |
|----|-----------|--------|-------|
| BT-151 | `ram:CategoryCode` | ✅ | Code TVA [UNTDID 5305] — BR-29 |
| BT-152 | `ram:RateApplicablePercent` | ⚠️ | Requis si CategoryCode=**S** ou **Z** — BR-30 |
| — | `ram:TypeCode` | ✅ | Toujours `VAT` |

### 12.6 Remises/Charges de ligne (SpecifiedLineTradeSettlement/SpecifiedTradeAllowanceCharge)

| BT | Champ CII | Statut | Notes |
|----|-----------|--------|-------|
| BT-136 | `ram:ActualAmount` (isCharge=false) | ⚠️ | Remise ligne si BG-27 |
| BT-139 | `ram:Reason` (isCharge=false) | ✅ | Motif remise ligne — BR-42 |
| BT-141 | `ram:ActualAmount` (isCharge=true) | ⚠️ | Charge ligne si BG-28 |
| BT-144 | `ram:Reason` (isCharge=true) | ✅ | Motif charge ligne — BR-44 |

### 12.7 Total de ligne (SpecifiedTradeSettlementLineMonetarySummation)

| BT | Champ CII | Statut | Notes |
|----|-----------|--------|-------|
| BT-131 | `ram:LineTotalAmount` | ✅ | BT-146 × BT-129 (± remises/charges ligne) — BR-24 |

### 12.8 Période de facturation par ligne (SpecifiedLineTradeSettlement/BillingSpecifiedPeriod)

| BT | Champ CII | Statut | Notes |
|----|-----------|--------|-------|
| BT-134 | `ram:StartDateTime` | ⬜ | Début période ligne |
| BT-135 | `ram:EndDateTime` | ⬜ | Fin période ligne |

---

## 13. RÈGLES MÉTIER (BR) — PRINCIPALES

### 13.1 Règles de présence (BR)

| BR | Règle |
|----|-------|
| BR-1 | `GuidelineSpecifiedDocumentContextParameter/ID` obligatoire |
| BR-2 | Numéro de facture (BT-1) obligatoire |
| BR-3 | Date d'émission (BT-2) obligatoire |
| BR-4 | Type de document (BT-3) obligatoire |
| BR-5 | Devise (BT-5) obligatoire — ISO 4217 |
| BR-6 | Nom vendeur (BT-27) obligatoire |
| BR-7 | Nom acheteur (BT-44) obligatoire |
| BR-8 | Pays vendeur (BT-40) obligatoire |
| BR-9 | Pays acheteur (BT-55) obligatoire |
| BR-10 | Au moins une ligne de facture |
| BR-11 | Au moins un bloc TVA (BG-23) |
| BR-12 | `LineTotalAmount` (BT-106) obligatoire |
| BR-13 | `TaxBasisTotalAmount` (BT-109) obligatoire |
| BR-14 | `GrandTotalAmount` (BT-112) obligatoire |
| BR-15 | `DuePayableAmount` (BT-115) obligatoire |

### 13.2 Règles de cohérence mathématique (BR-CO)

| BR-CO | Règle |
|-------|-------|
| BR-CO-3 | BT-131 = BT-146 × BT-129 (± remises/charges ligne) |
| BR-CO-4 | BT-106 = Σ BT-131 |
| BR-CO-5 | BT-109 = BT-106 + BT-108 − BT-107 |
| BR-CO-6 | BT-112 = BT-109 + BT-110 |
| BR-CO-7 | BT-115 = BT-112 − BT-113 + BT-114 |
| BR-CO-9 | BT-110 = Σ BT-117 (somme TVA tous groupes) |
| BR-CO-10 | BT-116 = BT-106 + Σ charges − Σ remises du groupe TVA |
| BR-CO-11 | BT-116 × BT-119 / 100 ≈ BT-117 (tolérance arrondi) |
| BR-CO-15 | BT-107 = Σ BT-92 |
| BR-CO-16 | BT-108 = Σ BT-99 |
| BR-CO-17 | BT-106 = Σ BT-131 de toutes les lignes |
| BR-CO-25 | Si BT-9 (date échéance) présente, BT-115 doit être ≥ 0 |
| BR-CO-26 | Si `SpecifiedLegalOrganization/ID` présent → `@schemeID` obligatoire |

### 13.3 Règles TVA standard (BR-S / BR-Z / BR-E / BR-AE / BR-O / BR-G / BR-K)

| Code | Règle |
|------|-------|
| BR-S-1 | Chaque groupe TVA S doit avoir BT-116, BT-117, BT-119 ≥ 0 |
| BR-S-2 | Si CategoryCode=**S** → vendeur doit avoir BT-31 (TVA) ou BT-32 (SIREN) |
| BR-S-9 | Si CategoryCode=**S** en ligne → BT-152 doit correspondre à un groupe BG-23 |
| BR-Z-1 | Taux zéro → BT-119 = 0, BT-117 = 0 |
| BR-E-1 | Exempté → BT-119 = 0, BT-117 = 0 |
| BR-E-2 | Exempté → BT-120 (motif exemption) obligatoire |
| BR-AE-1 | Autoliquidation → BT-117 = 0 |
| BR-AE-2 | Autoliquidation → acheteur doit avoir BT-48 (N° TVA) |
| BR-AE-3 | Autoliquidation → BT-120 (motif) obligatoire |
| BR-O-1 | Hors champ TVA → BT-117 = 0 |
| BR-O-4 | Hors champ TVA → BT-120 obligatoire |
| BR-G-1 | Export → BT-117 = 0 |
| BR-G-2 | Export → BT-120 obligatoire |
| BR-K-1 | Intracom → BT-117 = 0 |
| BR-K-2 | Intracom → acheteur doit avoir BT-48 |
| BR-K-3 | Intracom → BT-120 obligatoire |

### 13.4 Règles lignes de facture (BR-2x)

| Code | Règle |
|------|-------|
| BR-21 | Chaque ligne a un `LineID` (BT-126) unique |
| BR-22 | Chaque ligne a `BilledQuantity` (BT-129) |
| BR-23 | `@unitCode` de BT-129 obligatoire |
| BR-24 | Chaque ligne a `LineTotalAmount` (BT-131) |
| BR-25 | Chaque ligne a un nom de produit (BT-153) |
| BR-26 | Chaque ligne a un prix unitaire net (BT-146) |
| BR-27 | Si remise prix → prix brut (BT-148) obligatoire |
| BR-28 | Si BT-148 → BT-147 ≤ BT-148 |
| BR-29 | Chaque ligne a un code catégorie TVA (BT-151) |
| BR-30 | Si BT-151 = S ou Z → BT-152 obligatoire |

---

## 14. FORMATS ET CODES DE RÉFÉRENCE

### 14.1 Formats de date

| Format | Valeur `@format` | Exemple |
|--------|-----------------|---------|
| AAAAMMJJ | `102` | `20241215` |
| AAAAMM | `610` | `202412` |

### 14.2 Types de documents (BT-3) — UNTDID 1001

| Code | Signification |
|------|--------------|
| `380` | Facture commerciale ← **le plus courant** |
| `381` | Avoir / Note de crédit |
| `384` | Facture rectificative |
| `389` | Auto-facture |
| `751` | Relevé de facturation |

### 14.3 Catégories TVA (BT-118/BT-151) — UNTDID 5305

| Code | Signification |
|------|--------------|
| `S` | Taux standard (ex: 20%, 10%, 5,5%) |
| `Z` | Taux zéro |
| `E` | Exempté |
| `AE` | Autoliquidation |
| `K` | Intracom (acheteur UE assujetti) |
| `G` | Export hors UE |
| `O` | Hors champ TVA |
| `L` | IGIC (Canaries) |
| `M` | IPSI (Ceuta/Melilla) |

### 14.4 Moyens de paiement (BT-81) — UNTDID 4461 (principaux)

| Code | Signification |
|------|--------------|
| `10` | Espèces |
| `20` | Chèque |
| `30` | Virement crédit |
| `42` | Virement bancaire ← **le plus courant** |
| `48` | Carte bancaire |
| `49` | Prélèvement |
| `57` | Prélèvement SEPA standard |
| `58` | Virement SEPA |
| `59` | Prélèvement SEPA direct |
| `97` | Compensation |

### 14.5 Unités de mesure (BT-130) — UN/ECE Rec 20 (principales)

| Code | Signification |
|------|--------------|
| `H87` | Pièce / unité ← **le plus courant** |
| `HUR` | Heure |
| `DAY` | Jour |
| `WEE` | Semaine |
| `MON` | Mois |
| `KGM` | Kilogramme |
| `GRM` | Gramme |
| `MTR` | Mètre |
| `MTK` | Mètre carré |
| `LTR` | Litre |
| `TNE` | Tonne |
| `SET` | Ensemble |
| `C62` | Un (générique) |

### 14.6 Identifiants légaux (schemeID) — ISO 6523

| schemeID | Signification |
|----------|--------------|
| `0009` | SIRET (France) |
| `0002` | SIREN (France) |
| `VA` | N° TVA intracommunautaire |
| `FC` | SIREN (usage Factur-X France) |
| `EM` | Adresse email |
| `9930` | Numéro de taxe Allemagne |
| `0088` | GLN (logistique) |

---

## 15. FICHIER PDF/A-3 (pour PDF Factur-X)

| Critère | Obligatoire | Notes |
|---------|-------------|-------|
| PDF/A-3b minimum | ✅ | Profil d'archivage ISO 19005-3 |
| Pièce jointe XML nommée `factur-x.xml` | ✅ | Exactement ce nom |
| Relation d'association `AFRelationship = Alternative` | ✅ | Dans le dictionnaire de pièce jointe |
| Métadonnée XMP `fx:ConformanceLevel` | ✅ | `EN 16931` pour ce profil |
| Métadonnée XMP `fx:DocumentFileName` | ✅ | `factur-x.xml` |
| Métadonnée XMP `fx:DocumentType` | ✅ | `INVOICE` |
| Métadonnée XMP `fx:Version` | ✅ | `1.0` |
| Encodage XML UTF-8 sans BOM | ✅ | |
| Déclaration XML `<?xml version="1.0" encoding="UTF-8"?>` | ✅ | |

---

## 16. RÉSUMÉ — CE QUE VOTRE APP GÈRE DÉJÀ

| Critère | Géré ? | Fichier |
|---------|--------|---------|
| Numéro, date, type (380) | ✅ | `FacturxService::buildXml()` |
| Vendeur : nom, adresse, pays | ✅ | idem |
| Vendeur : SIRET (schemeID=0009) | ✅ | idem |
| Vendeur : N° TVA (schemeID=VA) | ✅ | idem |
| Vendeur : SIREN (schemeID=FC) | ✅ | idem |
| Acheteur : nom, adresse, pays | ✅ | idem |
| Lignes : désignation, prix, qté, TVA | ✅ | idem |
| Totaux : HT, TVA, TTC, net à payer | ✅ | idem |
| TVA groupée par taux | ✅ | idem |
| Allowances/charges document | ✅ | idem |
| Moyens de paiement (IBAN, BIC) | ✅ | idem |
| Date de livraison | ✅ | idem |
| Embedding XML dans PDF/A-3 | ✅ | `Writer::generate()` |
| **Motif obligatoire sur remises/charges (BR-33/BR-37)** | ⚠️ | À vérifier dans `FactureAllowanceCharge` |
| **Cohérence BT-115 = BT-112 − BT-113 (BR-CO-7)** | ⚠️ | `DuePayableAmount` = `GrandTotalAmount` (acomptes non gérés) |
| Acheteur : N° TVA (BT-48) | ⬜ | Non implémenté |
| Contacts vendeur/acheteur (BT-41/56) | ⬜ | Non implémenté |
| Référence commande acheteur (BT-13) | ⬜ | Champ `commande_acheteur` existe mais non injecté dans XML |
