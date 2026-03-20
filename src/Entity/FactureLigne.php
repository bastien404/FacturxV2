<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FactureLigne
{
#[ORM\Id]
#[ORM\GeneratedValue]
#[ORM\Column(type: "integer")]
private int $id;

#[ORM\ManyToOne(targetEntity: Facture::class, inversedBy: "lignes")]
#[ORM\JoinColumn(name: "facture_id", referencedColumnName: "id", nullable: false)]
private ?Facture $facture;

#[ORM\Column(type: "string", length: 255)]
private string $designation;

#[ORM\Column(type: "string", length: 255, nullable: true)]
private ?string $reference = null;

#[ORM\Column(type: "decimal", precision: 10, scale: 2)]
private float $quantite;

#[ORM\Column(type: "string", length: 20)]
private string $unite;

#[ORM\Column(type: "decimal", precision: 10, scale: 2)]
private float $prix_unitaire_ht;

#[ORM\Column(type: "decimal", precision: 5, scale: 2)]
private float $taux_tva;

#[ORM\Column(type: "string", length: 5, options: ["default" => "S"])]
private string $categorie_tva = 'S';

#[ORM\Column(type: "string", length: 255, nullable: true)]
private ?string $motif_exoneration = null;

#[ORM\Column(type: "decimal", precision: 10, scale: 2)]
private float $montant_ht;

#[ORM\Column(type: "decimal", precision: 10, scale: 2)]
private float $montant_tva;

#[ORM\Column(type: "decimal", precision: 10, scale: 2)]
private float $montant_ttc;

public function getId(): int { return $this->id; }
public function getFacture(): ?Facture { return $this->facture; }
public function setFacture(?Facture $facture): self { $this->facture = $facture; return $this; }
public function getDesignation(): string { return $this->designation; }
public function setDesignation(string $designation): self { $this->designation = $designation; return $this; }
public function getReference(): ?string { return $this->reference; }
public function setReference(?string $reference): self { $this->reference = $reference; return $this; }
public function getQuantite(): float { return $this->quantite; }
public function setQuantite(float $quantite): self { $this->quantite = $quantite; return $this; }
public function getUnite(): string { return $this->unite; }
public function setUnite(string $unite): self { $this->unite = $unite; return $this; }
public function getPrixUnitaireHt(): float { return $this->prix_unitaire_ht; }
public function setPrixUnitaireHt(float $prix_unitaire_ht): self { $this->prix_unitaire_ht = $prix_unitaire_ht; return $this; }
public function getTauxTva(): float { return $this->taux_tva; }
public function setTauxTva(float $taux_tva): self { $this->taux_tva = $taux_tva; return $this; }
public function getMontantHt(): float { return $this->montant_ht; }
public function setMontantHt(float $montant_ht): self { $this->montant_ht = $montant_ht; return $this; }
public function getMontantTva(): float { return $this->montant_tva; }
public function setMontantTva(float $montant_tva): self { $this->montant_tva = $montant_tva; return $this; }
public function getMontantTtc(): float { return $this->montant_ttc; }
public function setMontantTtc(float $montant_ttc): self { $this->montant_ttc = $montant_ttc; return $this; }
public function getCategorieTva(): string { return $this->categorie_tva; }
public function setCategorieTva(string $categorie_tva): self { $this->categorie_tva = $categorie_tva; return $this; }
public function getMotifExoneration(): ?string { return $this->motif_exoneration; }
public function setMotifExoneration(?string $motif_exoneration): self { $this->motif_exoneration = $motif_exoneration; return $this; }
}