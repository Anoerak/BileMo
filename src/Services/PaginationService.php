<?php

namespace App\Services;


use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Mapping\Entity;

class PaginationService extends ServiceEntityRepository
{
	private $entity;
	private $registry;

	public function __construct(ManagerRegistry $registry)
	{
		$this->registry = $registry;
	}

	public function initManagerRegistry(
		ManagerRegistry $registry,
		string $entity
	): void {
		parent::__construct($registry, $entity);
	}

	public function setEntityName(string $entity): void
	{
		$this->entity = $entity;
	}

	public function getEntityName(): string
	{
		return $this->entity . '::class';
	}

	public function findAllWithPagination(int $page, int $limit): array
	{
		$this->initManagerRegistry($this->registry, $this->getEntityName());

		$qb = $this->createQueryBuilder('b')
			->setFirstResult(($page - 1) * $limit)
			->setMaxResults($limit);

		return $qb->getQuery()->getResult();
	}
}
