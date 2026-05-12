<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    //    /**
    //     * @return Event[] Returns an array of Event objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Event
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    public function hasDateConflict(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?int $excludeEventId = null
    ): bool
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.startDate < :end')
            ->andWhere('e.endDate > :start')
            ->setParameter('start', $start)
            ->setParameter('end', $end);
    
        if ($excludeEventId !== null) {
            $qb->andWhere('e.id != :excludeId')
               ->setParameter('excludeId', $excludeEventId);
        }
    
        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function findForCalendar(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?string $type = null,
        ?string $status = null
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->where('e.startDate <= :end')
            ->andWhere('e.endDate >= :start')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('e.startDate', 'ASC');

        if ($type) {
            $qb->andWhere('e.type = :type')
               ->setParameter('type', $type);
        }

        if ($status) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function findUpcomingApproved(int $days = 30): array
    {
        $now = new \DateTimeImmutable();
        $future = $now->modify("+$days days");

        return $this->createQueryBuilder('e')
            ->where('e.status = :status')
            ->andWhere('e.startDate >= :now')
            ->andWhere('e.startDate <= :future')
            ->setParameter('status', Event::STATUS_APPROVED)
            ->setParameter('now', $now)
            ->setParameter('future', $future)
            ->orderBy('e.startDate', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    public function findFiltered(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?string $type = null,
        ?string $status = null
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->where('e.startDate <= :to')
            ->andWhere('e.endDate >= :from')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('e.startDate', 'ASC');

        if ($type) {
            $qb->andWhere('e.type = :type')
               ->setParameter('type', $type);
        }

        if ($status) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }
}
