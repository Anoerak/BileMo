<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use JMS\Serializer\Annotation\Type;

use JMS\Serializer\Annotation\Since;

use JMS\Serializer\Annotation\Groups;

use App\Repository\CustomerRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Hateoas\Configuration\Annotation as Hateoas;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;



/**
 * @Hateoas\Relation(
 *      "getAll",
 *      href = @Hateoas\Route(
 *          "app_customer",
 *          absolute = true
 *      ),
 *      exclusion = @Hateoas\Exclusion(
 *          groups = { "admin" },
 *          excludeIf = "expr(not is_granted('ROLE_ADMIN'))"
 *      )
 * )
 * 
 * @Hateoas\Relation(
 *      "get",
 *      href = @Hateoas\Route(
 *          "app_detail_customer",
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
 *      "delete",
 *      href = @Hateoas\Route(
 *         "app_delete_customer",
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
 *          "app_update_customer",
 *          parameters = { "id" = "expr(object.getId())" },
 *          absolute = true
 *      ),
 *      exclusion = @Hateoas\Exclusion(
 *          groups = { "admin" },
 *          excludeIf = "expr(not is_granted('ROLE_ADMIN'))"
 *      )
 *  )
 * 
 * @Hateoas\Relation(
 *     "app_create",
 *      href = @Hateoas\Route(
 *          "app_create_customer",
 *          absolute = true
 *      ),
 *      exclusion = @Hateoas\Exclusion(
 *          groups = { "admin" },
 *          excludeIf = "expr(not is_granted('ROLE_ADMIN'))"
 *      )
 * )
 * 
 */
#[ORM\Entity(repositoryClass: CustomerRepository::class)]
class Customer implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['customer'])]
    #[Since("1.0")]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['customer'])]
    #[Since("1.0")]
    #[Assert\NotBlank(message: 'Email is required')]
    private ?string $email = null;

    #[ORM\Column]
    #[Groups(['customer'])]
    #[Since("1.0")]
    #[Type(name: 'array')]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    #[Groups(['customer'])]
    #[Since("1.0")]
    #[Assert\NotBlank(groups: ['admin'], message: 'Password is required')]
    private ?string $password = null;

    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: User::class, orphanRemoval: true)]
    #[Groups(['customer'])]
    #[Since("1.0")]
    private Collection $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
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

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_GUEST
        $roles[] = 'ROLE_GUEST';
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

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
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setCustomer($this);
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getCustomer() === $this) {
                $user->setCustomer(null);
            }
        }

        return $this;
    }
}
