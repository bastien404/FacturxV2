# Documentation : Données Obligatoires pour la Génération Factur-X (Format XML)

Ce document liste l'exhaustivité des informations nécessaires au service \`FacturxService::buildXml()\` pour générer un fichier XML conforme aux directives européennes **EN16931** (Profils BASIC / EN16931) et à la **réforme française de la facturation électronique 2026**.
Toute donnée marquée comme **OBLIGATOIRE** provoquera un rejet sur le Portail Public de Facturation (PPF) ou par votre PDP si elle est manquante.

---

## 📄 1. Données d'En-tête de la Facture (Table \`Facture\`)

Ces données caractérisent le document comptable lui-même.

- **Numéro de facture** (\`numero_facture\`) : OBLIGATOIRE. Identifiant unique séquentiel.
- **Date d'émission** (\`date_facture\`) : OBLIGATOIRE. Date officielle de la facture (format YYYYMMDD dans le XML).
- **Type de facture** (\`type_facture\`) : OBLIGATOIRE. Code UNTDID 1001 (Ex: \`380\` pour une facture classique, \`381\` pour un avoir).
- **Devise** (\`devise\`) : OBLIGATOIRE. Code devise ISO 4217 (Ex: \`EUR\`).
- **Nature de l'opération** (\`nature_operation\`) : OBLIGATOIRE (Réforme 2026). Définit s'il s'agit d'une *"Livraison de biens"*, *"Prestation de services"*, ou *"Mixte"*.
- **TVA sur les débits** (\`tva_debits\`) : REQUIS SI APPLICABLE. Mention (True/False).
- **Date d'échéance** (\`date_echeance\`) : OBLIGATOIRE. Date limite de paiement attendue.

---

## 🏢 2. Informations du Vendeur / Fournisseur (Table \`Client\`)

Votre entreprise ou l'entité qui émet la facture.

- **Raison Sociale** (\`nom\`) : OBLIGATOIRE.
- **SIREN** (\`siren\`) : OBLIGATOIRE (France). Composé de 9 chiffres, c'est la clé de routage universelle de la réforme 2026.
- **Adresse complète** (OBLIGATOIRE en 4 champs détaillés) :
  - **Rue et N°** (\`adresse\`)
  - **Code Postal** (\`code_postal\`)
  - **Ville** (\`ville\`)
  - **Code Pays** (\`code_pays\`) : Format ISO-3166-1 (Ex: \`FR\`).
- **Numéro de TVA Intracommunautaire** (\`numero_tva\`) : OBLIGATOIRE (Sauf si sous le régime de la franchise en base de TVA).

---

## 🏬 3. Informations de l'Acheteur (Table \`Client\`)

Le cocontractant à qui s'adresse la facture.

- **Raison Sociale** (\`nom\`) : OBLIGATOIRE.
- **SIREN (Acheteur)** (\`siren\`) : OBLIGATOIRE (B2B Domestique 2026). Permet au PPF d'identifier où router la facture électronique.
- **Adresse complète** (OBLIGATOIRE en 4 champs détaillés) :
  - **Rue et N°** (\`adresse\`)
  - **Code Postal** (\`code_postal\`)
  - **Ville** (\`ville\`)
  - **Code Pays** (\`code_pays\`) : Ex: \`FR\`.

---

## 📦 4. Lignes de Détail de la Facture (Table \`FactureLigne\`)

Un document XML _Basic_ ou _EN16931_ **doit posséder au minimum une ligne** de produit/service.

- **Désignation** (\`designation\`) : OBLIGATOIRE. Libellé du produit ou service vendu.
- **Quantité facturée** (\`quantite\`) : OBLIGATOIRE.
- **Unité de mesure** (\`unite\`) : OBLIGATOIRE. Code standard UN/ECE Rec. 20 (Exemples : \`H87\` = Pièce, \`C62\` = Unité, \`LTR\` = Litre, \`KGM\` = Kilogramme).
- **Prix unitaire HT** (\`prix_unitaire_ht\`) : OBLIGATOIRE.
- **Taux de TVA** (\`taux_tva\`) : OBLIGATOIRE. Pourcentage exact (Ex: \`20.00\`).
- **Catégorie de TVA** (\`categorie_tva\`) : OBLIGATOIRE. Indique le statut fiscal de la ligne. (Ex: \`S\` pour Standard, \`E\` pour Exonéré, \`Z\` pour Taux zéro).
- **Montants finaux en base** : Montant HT de la ligne (\`montant_ht\`).

---

## 🔢 5. Données "Calculées" (Gérées automatiquement par le Service)

Ce ne sont pas des champs de base de données bruts de type "saisie", mais des calculs fiscaux complexes que produit le \`FacturxService\` à partir des lignes. Leur exactitude totale est OBLIGATOIRE.

- **Total par Taxe (BG-23)** : Le XML groupe et cumule automatiquement les montants imposables par couple \`Catégorie + Taux de TVA\`.
- **Totaux globaux de la facture** : 
  - \`LineTotalAmount\` (Total HT des lignes)
  - \`TaxBasisTotalAmount\` (Base Imposable Globale HT)
  - \`TaxTotalAmount\` (Total global de la TVA)
  - \`GrandTotalAmount\` (Total global TTC)
  - \`DuePayableAmount\` (Reste à Payer, généralement équivalent au TTC).

*(Fichier auto-généré sur les bases des spécifications AIFE PPF / Norme NF EN 16931).*
