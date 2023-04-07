<?php

namespace App\Services;

use App\Entity\User;


use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;


class PaginationService extends ServiceEntityRepository
{
	private $entity;
	private $registry;

	public function __construct(ManagerRegistry $registry)
	{
		$this->registry = $registry;
	}

	public function initManagerRegistry($registry, $entity)
	{
		parent::__construct($registry, $entity);
	}

	public function setEntityName($entity)
	{
		$this->entity = $entity;
	}

	public function getEntityName()
	{
		return $this->entity . '::class';
	}

	public function findAllWithPagination($page, $limit)
	{
		$this->initManagerRegistry($this->registry, $this->getEntityName());

		$qb = $this->createQueryBuilder('b')
			->setFirstResult(($page - 1) * $limit)
			->setMaxResults($limit);

		return $qb->getQuery()->getResult();
	}
}
