<?php

namespace App\Entity;

use App\Repository\SettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettingRepository::class)]
#[ORM\Table(name: 'setting')]
class Setting
{
    #[ORM\Id]
    #[ORM\Column(name: 'setting_key', type: 'string', length: 100)]
    private string $key;

    #[ORM\Column(name: 'setting_value', type: 'text', nullable: true)]
    private ?string $value = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $label = null;

    public function __construct(string $key = '', ?string $value = null, ?string $label = null)
    {
        $this->key = $key;
        $this->value = $value;
        $this->label = $label;
    }

    public function getKey(): string { return $this->key; }
    public function setKey(string $key): self { $this->key = $key; return $this; }
    public function getValue(): ?string { return $this->value; }
    public function setValue(?string $value): self { $this->value = $value; return $this; }
    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $label): self { $this->label = $label; return $this; }
}
