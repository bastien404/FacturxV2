# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

FacturxV2 is a Symfony 6.4 / PHP 8.1+ web application that generates Factur-X/ZUGFeRD-compliant electronic invoices: PDF files with embedded EN16931-profile XML payloads. It manages clients, invoice lines with multi-rate VAT, allowances/charges, and UNTDID 4461 payment methods.

## Commands

```bash
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
symfony server:start
php bin/console cache:clear

# Run all tests
php bin/phpunit

# Run a single test file
php bin/phpunit tests/path/to/SomeTest.php
```

## Architecture

The core flow is:

1. `FactureController` handles HTTP routes (`/`, `/new`, `/{id}`, `/{id}/edit`, `/{id}/download`).
2. Symfony Form types (`FactureType`, `FactureLigneType`) validate and hydrate entities (`Facture`, `FactureLigne`, `FactureAllowanceCharge`, `PaymentMeans`, `Client`).
3. Doctrine ORM persists to the database (PostgreSQL via Docker Compose).
4. On download, `FacturxService::buildXml()` constructs the EN16931 XML (line items, seller/buyer details with SIREN/SIRET/VAT, allowances, tax summaries, payment terms) using `atgp/factur-x`.
5. `FacturxService::buildPdfFacturX()` renders the invoice HTML template to PDF via Dompdf, then embeds the XML as an attachment using the Factur-X writer. The output is saved under `public/`.

`FacturxService` receives `$projectDir` via service injection (`config/services.yaml`) to locate the `public/` output directory and the PDF template.

## Coding Guidelines

- Use PHP 8 attributes for Doctrine (`#[ORM\Entity]`), routing (`#[Route]`), and validation (`#[Assert\NotBlank]`) — not DocBlock annotations.
- Business logic (XML packaging, VAT calculations) belongs in `src/Service/`, not controllers.
- Validate ISO data (ISO 3166-1 alpha-2 countries, ISO 4217 currencies, UNTDID codes) via Symfony constraints on entities.
- Frontend interactions use Symfony UX (Stimulus/Turbo) with AssetMapper — not Webpack Encore.
- All invoice features must remain compliant with the Factur-X/ZUGFeRD EN16931 profile.
