-- ==============================================================================
-- FIXTURES / DONNÉES FICTIVES POUR TESTER LA GÉNÉRATION FACTUR-X (PROFIL EN16931)
-- ==============================================================================

-- 1. CRÉATION DU FOURNISSEUR (L'émetteur de la facture)
-- Remarque : Tous les champs "NOT NULL" (siren, siret, adresse, etc.) sont remplis.
INSERT INTO client (nom, siren, siret, numero_tva, adresse, ville, code_postal, code_pays, email, telephone, tva) 
VALUES (
    'Tech Solutions Cloud', 
    '111222333', 
    '11122233300045', 
    'FR11111222333', 
    '10 Avenue de l''Innovation', 
    'Bordeaux', 
    '69001', 
    'FR', 
    'compta@techsolutions.com', 
    '0102030405', 
    NULL
);

-- 2. CRÉATION DE L'ACHETEUR (Votre client)
-- Parfaitement complet pour B2B domestique
INSERT INTO client (nom, siren, siret, numero_tva, adresse, ville, code_postal, code_pays, email, telephone, tva) 
VALUES (
    'Mégacorporation SA', 
    '999888777', 
    '99988877700010', 
    'FR99999888777', 
    '5 Place de la République', 
    'Paris', 
    '75003', 
    'FR', 
    'facturation@megacorp.fr', 
    '0809101112',
    NULL
);

-- 3. CRÉATION DE LA FACTURE
-- Liaison avec ID Fournisseur (1) et Acheteur (2). (Ajustez les IDs si vous avez déjà des clients en base).
-- type_facture = 380 (Facture standard).
-- nature_operation = 'services'
INSERT INTO facture (fournisseur_id, acheteur_id, numero_facture, date_facture, type_facture, devise, net_apayer, nature_operation, tva_debits, charges)
VALUES (
    (SELECT MAX(id)-1 FROM client), -- Fournisseur créé juste avant
    (SELECT MAX(id) FROM client),   -- Acheteur créé juste avant
    'FA-TEST-2026-001', 
    '2026-03-24 10:00:00', 
    '380', 
    'EUR', 
    3600.00, 
    'services', 
    0, 
    0
);

-- 4. INSERTION DES LIGNES DE FACTURE
-- Ligne 1 : Prestation de service unitaire
-- L'unité 'H87' représente "Pièce" ou "Unité" en codification internationale.
-- Catégorie TVA 'S' = Standard.
INSERT INTO facture_ligne (facture_id, designation, reference, quantite, unite, prix_unitaire_ht, taux_tva, categorie_tva, montant_ht, montant_tva, montant_ttc) 
VALUES (
    (SELECT MAX(id) FROM facture), 
    'Développement Application Mobile', 
    'DEV-MOB-01', 
    1.0, 
    'H87', 
    1500.00, 
    20.00, 
    'S', 
    1500.00, 
    300.00, 
    1800.00
);

-- Ligne 2 : Forfait jours (Service)
-- L'unité 'DAY' représente "Jour"
INSERT INTO facture_ligne (facture_id, designation, reference, quantite, unite, prix_unitaire_ht, taux_tva, categorie_tva, montant_ht, montant_tva, montant_ttc) 
VALUES (
    (SELECT MAX(id) FROM facture), 
    'Consulting UX/UI (Jours)', 
    'CONS-UX-01', 
    3.0, 
    'DAY', 
    500.00, 
    20.00, 
    'S', 
    1500.00, 
    300.00, 
    1800.00
);
