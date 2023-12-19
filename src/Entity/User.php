<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Repository\UserRepository;

use JMS\Serializer\Annotation\Since;

use JMS\Serializer\Annotation\Groups;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Hateoas\Configuration\Annotation as Hateoas;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;


/**
 * @Hateoas\Relation(
 *     "getAll",
 *      href = @Hateoas\Route(
 *         "app_user",
 *         absolute = true
 *      ),
 *      exclusion = @Hateoas\Exclusion(
 *          groups = { "customer" },
 *          excludeIf = "expr(not is_granted('ROLE_ADMIN'))"
 *      )
 * )
 * 
 * @Hateoas\Relation(
 *      "getOne",
 *      href = @Hateoas\Route(
 *          "app_detail_user",
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
 *      "delete",
 *      href = @Hateoas\Route(
 *          "app_delete_user",
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
 *          "app_update_user",
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
 *          "app_create_user",
 *          absolute = true
 *      ),
 *      exclusion = @Hateoas\Exclusion(
 *          groups = { "customer" },
 *          excludeIf = "expr(not is_granted('ROLE_ADMIN'))",
 *      )
 * )
 * 
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user'])]
    #[Since('1.0')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['user'])]
    #[Since('1.0')]
    private ?string $email = null;

    /**
     * @var string The hashed password
     */
    #[ORM\Column(length: 255)]
    #[Groups(['password'])]
    #[Since('1.0')]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user'])]
    #[Since('1.0')]
    private ?string $username = null;

    #[ORM\ManyToMany(targetEntity: Product::class, mappedBy: 'user')]
    #[Groups(['user'])]
    #[Since('1.0')]
    private Collection $products;

    #[ORM\ManyToOne(inversedBy: 'users', targetEntity: Customer::class, cascade: ['persist'])]
    #[Groups(['user'])]
    #[Since('1.0')]
    private ?Customer $customer = null;

    #[ORM\Column]
    #[Groups(['user'])]
    #[Since('1.0')]
    private array $roles = [];

    public function __construct()
    {
        $this->products = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_GUEST
        $roles[] = 'ROLE_GUEST';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): self
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->addUser($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): self
    {
        if ($this->products->removeElement($product)) {
            $product->removeUser($this);
        }

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }
}
