<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
class Client
{
#[ORM\Id]
#[ORM\GeneratedValue]
#[ORM\Column(type: "integer")]
private int $id;

#[ORM\Column(type: "string", length: 255)]
private string $nom;

#[ORM\Column(type: "string", length: 20)]
private string $siren;

#[ORM\Column(type: "string", length: 20)]
private string $siret;

#[ORM\Column(type: "string", length: 50, nullable: true)]
private ?string $tva = null;

#[ORM\Column(type: "string", length: 255, nullable: true)]
private ?string $email = null;

#[ORM\Column(type: "string", length: 255)]
private string $adresse;

#[ORM\Column(type: "string", length: 100)]
private string $ville;

#[ORM\Column(type: "string", length: 20)]
private string $code_postal;

#[ORM\Column(type: "string", length: 2)]
private string $code_pays;

#[ORM\Column(type: "string", length: 50, nullable: true)]
private ?string $telephone = null;

#[ORM\Column(type: "string", length: 255)]
private string $numero_tva;

#[ORM\OneToMany(mappedBy: "fournisseur", targetEntity: Facture::class)]
private Collection $facturesFournisseur;

#[ORM\OneToMany(mappedBy: "acheteur", targetEntity: Facture::class)]
private Collection $facturesAcheteur;

public function __construct()
{
$this->facturesFournisseur = new ArrayCollection();
$this->facturesAcheteur = new ArrayCollection();
}

public function getId(): int { return $this->id; }
public function getNom(): string { return $this->nom; }
public function setNom(string $nom): self { $this->nom = $nom; return $this; }
public function getSiren(): string { return $this->siren; }
public function setSiren(string $siren): self { $this->siren = $siren; return $this; }
public function getSiret(): string { return $this->siret; }
public function setSiret(string $siret): self { $this->siret = $siret; return $this; }
public function getTva(): ?string { return $this->tva; }
public function setTva(?string $tva): self { $this->tva = $tva; return $this; }
public function getEmail(): ?string { return $this->email; }
public function setEmail(?string $email): self { $this->email = $email; return $this; }
public function getAdresse(): string { return $this->adresse; }
public function setAdresse(string $adresse): self { $this->adresse = $adresse; return $this; }
public function getVille(): string { return $this->ville; }
public function setVille(string $ville): self { $this->ville = $ville; return $this; }
public function getCodePostal(): string { return $this->code_postal; }
public function setCodePostal(string $code_postal): self { $this->code_postal = $code_postal; return $this; }
public function getCodePays(): string { return $this->code_pays; }
public function setCodePays(string $code_pays): self { $this->code_pays = $code_pays; return $this; }
public function getTelephone(): ?string { return $this->telephone; }
public function setTelephone(?string $telephone): self { $this->telephone = $telephone; return $this; }
public function getNumeroTva(): string { return $this->numero_tva; }
public function setNumeroTva(string $numero_tva): self { $this->numero_tva = $numero_tva; return $this; }

public function getFacturesFournisseur(): Collection { return $this->facturesFournisseur; }
public function getFacturesAcheteur(): Collection { return $this->facturesAcheteur; }
}