<?php

namespace App\Repository;

use App\Entity\Facture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Facture>
 */
class FactureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Facture::class);
    }

    /**
     * Sauvegarde une facture en base
     */
    public function save(Facture $facture, bool $flush = false): void
    {
        $this->_em->persist($facture);

        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * Supprime une facture en base
     */
    public function remove(Facture $facture, bool $flush = false): void
    {
        $this->_em->remove($facture);

        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * Trouver une facture par son numero
     */
    public function findOneByNumero(string $numero): ?Facture
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.numero_facture = :num')
            ->setParameter('num', $numero)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouver toutes les factures d'un fournisseur donne (par SIREN du Client)
     */
    public function findByFournisseur(string $sirenFournisseur): array
    {
        return $this->createQueryBuilder('f')
            ->join('f.fournisseur', 'c')
            ->andWhere('c.siren = :siren')
            ->setParameter('siren', $sirenFournisseur)
            ->orderBy('f.date_facture', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Factures en retard (echeance depassee et montant > 0)
     */
    public function findOverdue(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.date_echeance < :now')
            ->andWhere('f.net_apayer > 0')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('f.date_echeance', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
