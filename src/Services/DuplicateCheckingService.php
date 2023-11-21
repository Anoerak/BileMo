<?php

namespace App\Services;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DuplicateCheckingService
{
	private $entityManager;

	public function __construct(EntityManagerInterface $entityManager)
	{
		$this->entityManager = $entityManager;
	}

	public function checkForExistingEntry($entity, $field, $value)
	{
		$entityExist = $this->entityManager->getRepository($entity)->findOneBy([$field => $value]);
		if ($entityExist) {
			return new JsonResponse(['message' => 'This ' . $field . ' is already used'], Response::HTTP_BAD_REQUEST);
		}
	}
}
