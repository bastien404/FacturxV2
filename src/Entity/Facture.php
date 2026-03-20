<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
class Facture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: "facturesFournisseur")]
    #[ORM\JoinColumn(name: "fournisseur_id", referencedColumnName: "id")]
    private ?Client $fournisseur = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: "facturesAcheteur")]
    #[ORM\JoinColumn(name: "acheteur_id", referencedColumnName: "id")]
    private ?Client $acheteur = null;

    #[ORM\Column(type: "string", length: 50)]
    private string $numero_facture;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date_facture;

    #[ORM\Column(type: "string", length: 10)]
    private string $type_facture;

    #[ORM\Column(type: "string", length: 3)]
    private string $devise;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private float $net_apayer;

    #[ORM\Column(type: "date", nullable: true)]
    private ?\DateTimeInterface $date_echeance = null;

    #[ORM\Column(type: "date", nullable: true)]
    private ?\DateTimeInterface $date_livraison = null;

    #[ORM\Column(type: "string", length: 10, nullable: true)]
    private ?string $mode_paiement = null;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $reference_paiement = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $taxes = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(type: "float")]
    private float $charges = 0.0;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $commande_acheteur = null;

    #[ORM\Column(type: "string", length: 20)]
    private string $nature_operation;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $tva_debits = false;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $livraison_adresse = null;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $livraison_ville = null;

    #[ORM\Column(type: "string", length: 20, nullable: true)]
    private ?string $livraison_code_postal = null;

    #[ORM\Column(type: "string", length: 2, nullable: true)]
    private ?string $livraison_code_pays = null;

    #[ORM\OneToMany(mappedBy: "facture", targetEntity: FactureAllowanceCharge::class)]
    private Collection $allowanceCharges;

    #[ORM\OneToMany(mappedBy: "facture", targetEntity: FactureLigne::class)]
    private Collection $lignes;

    #[ORM\OneToMany(mappedBy: "facture", targetEntity: PaymentMeans::class)]
    private Collection $paymentMeans;

    public function __construct()
    {
        $this->allowanceCharges = new ArrayCollection();
        $this->lignes = new ArrayCollection();
        $this->paymentMeans = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }
    public function getFournisseur(): ?Client
    {
        return $this->fournisseur;
    }
    public function setFournisseur(?Client $fournisseur): self
    {
        $this->fournisseur = $fournisseur;
        return $this;
    }
    public function getAcheteur(): ?Client
    {
        return $this->acheteur;
    }
    public function setAcheteur(?Client $acheteur): self
    {
        $this->acheteur = $acheteur;
        return $this;
    }
    public function getNumeroFacture(): string
    {
        return $this->numero_facture;
    }
    public function setNumeroFacture(string $numero_facture): self
    {
        $this->numero_facture = $numero_facture;
        return $this;
    }
    public function getDateFacture(): \DateTimeInterface
    {
        return $this->date_facture;
    }
    public function setDateFacture(\DateTimeInterface $date_facture): self
    {
        $this->date_facture = $date_facture;
        return $this;
    }
    public function getTypeFacture(): string
    {
        return $this->type_facture;
    }
    public function setTypeFacture(string $type_facture): self
    {
        $this->type_facture = $type_facture;
        return $this;
    }
    public function getDevise(): string
    {
        return $this->devise;
    }
    public function setDevise(string $devise): self
    {
        $this->devise = $devise;
        return $this;
    }
    public function getNetApayer(): float
    {
        return $this->net_apayer;
    }
    public function setNetApayer(float $net_apayer): self
    {
        $this->net_apayer = $net_apayer;
        return $this;
    }
    public function getDateEcheance(): ?\DateTimeInterface
    {
        return $this->date_echeance;
    }
    public function setDateEcheance(?\DateTimeInterface $date_echeance): self
    {
        $this->date_echeance = $date_echeance;
        return $this;
    }
    public function getDateLivraison(): ?\DateTimeInterface
    {
        return $this->date_livraison;
    }
    public function setDateLivraison(?\DateTimeInterface $date_livraison): self
    {
        $this->date_livraison = $date_livraison;
        return $this;
    }
    public function getModePaiement(): ?string
    {
        return $this->mode_paiement;
    }
    public function setModePaiement(?string $mode_paiement): self
    {
        $this->mode_paiement = $mode_paiement;
        return $this;
    }
    public function getReferencePaiement(): ?string
    {
        return $this->reference_paiement;
    }
    public function setReferencePaiement(?string $reference_paiement): self
    {
        $this->reference_paiement = $reference_paiement;
        return $this;
    }
    public function getTaxes(): ?array
    {
        return $this->taxes;
    }
    public function setTaxes(?array $taxes): self
    {
        $this->taxes = $taxes;
        return $this;
    }
    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }
    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;
        return $this;
    }
    public function getCharges(): float
    {
        return $this->charges;
    }
    public function setCharges(float $charges): self
    {
        $this->charges = $charges;
        return $this;
    }
    public function getCommandeAcheteur(): ?string
    {
        return $this->commande_acheteur;
    }
    public function setCommandeAcheteur(?string $commande_acheteur): self
    {
        $this->commande_acheteur = $commande_acheteur;
        return $this;
    }

    public function getNatureOperation(): string { return $this->nature_operation; }
    public function setNatureOperation(string $nature_operation): self { $this->nature_operation = $nature_operation; return $this; }
    public function isTvaDebits(): bool { return $this->tva_debits; }
    public function setTvaDebits(bool $tva_debits): self { $this->tva_debits = $tva_debits; return $this; }
    public function getLivraisonAdresse(): ?string { return $this->livraison_adresse; }
    public function setLivraisonAdresse(?string $livraison_adresse): self { $this->livraison_adresse = $livraison_adresse; return $this; }
    public function getLivraisonVille(): ?string { return $this->livraison_ville; }
    public function setLivraisonVille(?string $livraison_ville): self { $this->livraison_ville = $livraison_ville; return $this; }
    public function getLivraisonCodePostal(): ?string { return $this->livraison_code_postal; }
    public function setLivraisonCodePostal(?string $livraison_code_postal): self { $this->livraison_code_postal = $livraison_code_postal; return $this; }
    public function getLivraisonCodePays(): ?string { return $this->livraison_code_pays; }
    public function setLivraisonCodePays(?string $livraison_code_pays): self { $this->livraison_code_pays = $livraison_code_pays; return $this; }

    public function getAllowanceCharges(): Collection
    {
        return $this->allowanceCharges;
    }
    public function addAllowanceCharge(FactureAllowanceCharge $ac): self
    {
        if (!$this->allowanceCharges->contains($ac)) {
            $this->allowanceCharges[] = $ac;
            $ac->setFacture($this);
        }
        return $this;
    }
    public function removeAllowanceCharge(FactureAllowanceCharge $ac): self
    {
        if ($this->allowanceCharges->removeElement($ac)) {
            if ($ac->getFacture() === $this) $ac->setFacture(null);
        }
        return $this;
    }

    public function getLignes(): Collection
    {
        return $this->lignes;
    }
    public function addLigne(FactureLigne $ligne): self
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes[] = $ligne;
            $ligne->setFacture($this);
        }
        return $this;
    }
    public function removeLigne(FactureLigne $ligne): self
    {
        if ($this->lignes->removeElement($ligne)) {
            if ($ligne->getFacture() === $this) $ligne->setFacture(null);
        }
        return $this;
    }

    public function getTotalHt(): float
    {
        $total = 0.0;
        foreach ($this->getLignes() as $ligne) {
            $total += $ligne->getMontantHt();
        }
        return $total;
    }

    public function getTotalTva(): float
    {
        $total = 0.0;
        foreach ($this->getLignes() as $ligne) {
            $total += $ligne->getMontantTva();
        }
        return $total;
    }

    public function getTotalTtc(): float
    {
        $total = 0.0;
        foreach ($this->getLignes() as $ligne) {
            $total += $ligne->getMontantTtc();
        }
        return $total;
    }

    public function getPaymentMeans(): Collection
    {
        return $this->paymentMeans;
    }
    public function addPaymentMeans(PaymentMeans $means): self
    {
        if (!$this->paymentMeans->contains($means)) {
            $this->paymentMeans[] = $means;
            $means->setFacture($this);
        }
        return $this;
    }

    public function getTaxBasisTotal(): float
    {
        $base = $this->getTotalHt();

        foreach ($this->getAllowanceCharges() as $item) {
            if ($item->getIsCharge()) {
                $base += $item->getAmount();
            } else {
                $base -= $item->getAmount();
            }
        }

        return max($base, 0);
    }
    public function removePaymentMeans(PaymentMeans $means): self
    {
        if ($this->paymentMeans->removeElement($means)) {
            if ($means->getFacture() === $this) $means->setFacture(null);
        }
        return $this;
    }
}
