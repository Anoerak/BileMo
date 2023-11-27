<?php

namespace App\Entity;

use App\Repository\ProductRepository;

use Hateoas\Configuration\Annotation as Hateoas;

use Doctrine\DBAL\Types\Types;

use Doctrine\ORM\Mapping as ORM;

use JMS\Serializer\Annotation\Groups;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @Hateoas\Relation(
 *    "getAll",
 *      href = @Hateoas\Route(
 *          "app_product",
 *          absolute = true
 *      ),
 *      exclusion = @Hateoas\Exclusion(
 *          groups = { "guest" },
 *          excludeIf = "expr(not is_granted('ROLE_GUEST'))"
 *      )
 * )
 * 
 * @Hateoas\Relation(
 *      "getOne",
 *      href = @Hateoas\Route(
 *          "app_detail_product",
 *          parameters = { "id" = "expr(object.getId())" },
 *          absolute = true
 *      ),
 *      exclusion = @Hateoas\Exclusion(
 *          groups = { "guest" },
 *          excludeIf = "expr(not is_granted('ROLE_GUEST'))"
 *      )
 * )
 * 
 * @Hateoas\Relation(
 *      "delete",
 *      href = @Hateoas\Route(
 *          "app_delete_product",
 *          parameters = { "id" = "expr(object.getId())" },
 *          absolute = true
 *      ),
 *      exclusion = @Hateoas\Exclusion(
 *          groups = { "admin" },
 *          excludeIf = "expr(not is_granted('ROLE_ADMIN'))"
 *      )
 * )
 * 
 * @Hateoas\Relation(
 *      "update",
 *      href = @Hateoas\Route(
 *          "app_update_product",
 *          parameters = { "id" = "expr(object.getId())" },
 *          absolute = true
 *      ),
 *      exclusion = @Hateoas\Exclusion(
 *          groups = { "admin" },
 *          excludeIf = "expr(not is_granted('ROLE_ADMIN'))"
 *      )
 * )
 * 
 * @Hateoas\Relation(
 *      "create",
 *      href = @Hateoas\Route(
 *          "app_create_product",
 *          absolute = true
 *      ),
 *      exclusion = @Hateoas\Exclusion(
 *          groups = { "admin" },
 *          excludeIf = "expr(not is_granted('ROLE_ADMIN'))"
 *      )
 * )
 * 
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['admin', 'user', 'guest'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['admin', 'user', 'guest'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['admin', 'user', 'guest'])]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin', 'user', 'guest'])]
    private ?string $price = null;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'products', cascade: ['persist'])]
    #[Groups(['admin', 'user'])]
    private Collection $owner;

    public function __construct()
    {
        $this->owner = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): self
    {
        $this->price = $price;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getOwner(): Collection
    {
        return $this->owner;
    }

    public function addOwner(User $owner): self
    {
        if (!$this->owner->contains($owner)) {
            $this->owner->add($owner);
        }

        return $this;
    }

    public function removeOwner(User $owner): self
    {
        $this->owner->removeElement($owner);

        return $this;
    }
}
