<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;

use Doctrine\ORM\Mapping as ORM;

use JMS\Serializer\Annotation\Since;

use App\Repository\ProductRepository;

use JMS\Serializer\Annotation\Groups;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Hateoas\Configuration\Annotation as Hateoas;

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
 *          groups = { "customer" },
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
 *          groups = { "customer" },
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
 *          groups = { "customer" },
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
    #[Groups(['product'])]
    #[Since("1.0")]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['product'])]
    #[Since("1.0")]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['product'])]
    #[Since("1.0")]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['product'])]
    #[Since("1.0")]
    private ?string $price = null;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'products', cascade: ['persist'])]
    #[Groups(['product'])]
    #[Since("1.0")]
    private Collection $user;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['getComments'])]
    #[Since("2.0")]
    private ?string $comments = null;

    public function __construct()
    {
        $this->user = new ArrayCollection();
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
    public function getuser(): Collection
    {
        return $this->user;
    }

    public function adduser(User $user): self
    {
        if (!$this->user->contains($user)) {
            $this->user->add($user);
        }

        return $this;
    }

    public function removeuser(User $user): self
    {
        $this->user->removeElement($user);

        return $this;
    }

    public function getComments(): ?string
    {
        return $this->comments;
    }

    public function setComments(?string $comments): static
    {
        $this->comments = $comments;

        return $this;
    }
}
