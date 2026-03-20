<?php

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    /**
     * Récupère la valeur d'un paramètre, ou $default s'il n'existe pas.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $setting = $this->find($key);

        return $setting?->getValue() ?? $default;
    }

    /**
     * Crée ou met à jour un paramètre.
     */
    public function set(string $key, ?string $value, ?string $label = null): void
    {
        $em = $this->getEntityManager();
        $setting = $this->find($key);

        if (!$setting) {
            $setting = new Setting($key, $value, $label);
            $em->persist($setting);
        } else {
            $setting->setValue($value);
            if ($label !== null) {
                $setting->setLabel($label);
            }
        }

        $em->flush();
    }
}
