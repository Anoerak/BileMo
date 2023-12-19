<?php

namespace App\Services;

use App\Entity\User;
use App\Entity\Customer;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UpdateEntitiesService
{
	private $em;
	private $encoder;

	public function __construct(EntityManagerInterface $em, UserPasswordHasherInterface $encoder)
	{
		$this->em = $em;
		$this->encoder = $encoder;
	}

	public function update(object $entity, object $data, object $request)
	{
		switch (get_class($entity)) {
				/* #region Customer Entity */
			case 'App\Entity\Customer':
				// We check the data and update the customer if anything changed
				if ($data->getEmail() !== null && $entity->getEmail() !== $data->getEmail()) {
					$entity->setEmail($data->getEmail());
				}
				if ($data->getPassword() !== null && $entity->getPassword() !== $data->getPassword()) {
					$entity->setPassword($this->encoder->hashPassword($entity, $data->getPassword()));
				}
				if ($data->getRoles() !== null) {
					$entity->setRoles($data->getRoles());
				}
				// We get the arrays from the request
				$context = $request->toArray();
				// We check if there are Users affected to this new Customer
				if (isset($context['user'])) {
					foreach ($context['user'] as $userId) {
						$user = $this->em->getRepository(User::class)->findBy(['id' => $userId]);
						if (!$user) {
							return new JsonResponse(['message' => 'This user does not exist'], Response::HTTP_BAD_REQUEST);
						}
						$entity->addUser($user[0]);
					}
				}
				break;
				/* #endregion */

				/* #region User Entity */
			case 'App\Entity\User':
				// We check the data and update the customer if anything changed
				if ($data->getEmail() !== null && $entity->getEmail() !== $data->getEmail()) {
					$entity->setEmail($data->getEmail());
				}
				if ($data->getUsername() !== null && $entity->getUsername() !== $data->getUsername()) {
					$entity->setUsername($data->getUsername());
				}
				if (
					$data->getPassword() !== null && $entity->getPassword() !== $data->getPassword()
				) {
					$entity->setPassword($this->encoder->hashPassword($entity, $data->getPassword()));
				}
				if ($data->getRoles() !== null && $entity->getRoles() !== $data->getRoles()) {
					$entity->setRoles($data->getRoles());
				}
				// We get the arrays from the request
				$context = $request->toArray();
				if (isset($context['customer'])) {
					$customerId = $context['customer'][0];
					$customer = $this->em->getRepository(Customer::class)->findBy(['id' => $customerId]);
					if (!$customer) {
						return new JsonResponse(['message' => 'This customer does not exist'], Response::HTTP_BAD_REQUEST);
					}
					$entity->setCustomer($customer[0]);
				}
				break;
				/* #endregion */

				/* #region Product Entity */
			case 'App\Entity\Product':
				// We check the data and update the customer if anything changed
				if (
					$data->getName() !== null && $entity->getName() !== $data->getName()
				) {
					$entity->setName($data->getName());
				}
				if ($data->getDescription() !== null && $entity->getDescription() !== $data->getDescription()) {
					$entity->setDescription($data->getDescription());
				}
				if (
					$data->getPrice() !== null && $entity->getPrice() !== $data->getPrice()
				) {
					$entity->setPrice($data->getPrice());
				}
				// We get the arrays from the request
				$context = $request->toArray();
				if (isset($context['user'])) {
					foreach ($context['user'] as $userId) {
						$user = $this->em->getRepository(User::class)->findBy(['id' => $userId]);
						if (!$user) {
							return new JsonResponse(['message' => 'This user does not exist'], Response::HTTP_BAD_REQUEST);
						}
						$entity->addUser($user[0]);
					}
				}
				break;
				/* #endregion */
			default:
				break;
		}
	}
}
