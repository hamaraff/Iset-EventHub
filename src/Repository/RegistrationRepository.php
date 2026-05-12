<?php

namespace App\Repository;

use App\Entity\Registration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Organization;
use App\Entity\Event;
use App\Entity\User;

/**
 * @extends ServiceEntityRepository<Registration>
 */
class RegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Registration::class);
    }

    //    /**
    //     * @return Registration[] Returns an array of Registration objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Registration
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    public function findByEventAndUser(Event $event, User $user): ?Registration
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.event = :event')
            ->andWhere('r.user = :user')
            ->setParameter('event', $event)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
    public function hasActiveRegistration(Event $event, User $user, array $activeStatuses): bool
    {
        if ($activeStatuses === []) return false;

        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.event = :event')
            ->andWhere('r.user = :user')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('event', $event)
            ->setParameter('user', $user)
            ->setParameter('statuses', $activeStatuses)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
    public function hasActiveOrganizationRegistration(Event $event, Organization $org, array $activeStatuses): bool
    {
        if ($activeStatuses === []) {
            return false;
        }
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.event = :event')
            ->andWhere('r.organization = :org')
            ->andWhere('r.type = :type')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('event', $event)
            ->setParameter('org', $org)
            ->setParameter('type', Registration::TYPE_ORGANIZATION)
            ->setParameter('statuses', $activeStatuses)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
