<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/events')]
final class EventController extends AbstractController
{
    // List all approved events (public)
    #[Route('', name: 'event_list', methods: ['GET'])]
    public function index(EventRepository $eventRepository): Response
    {
        $events = $eventRepository->createQueryBuilder('e')
            ->where('e.status = :status')
            ->andWhere('e.endDate >= :now')
            ->setParameter('status', Event::STATUS_APPROVED)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('e.startDate', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('event/index.html.twig', [
            'events' => $events,
        ]);
    }

    // View single event details
    #[Route('/{id}', name: 'event_show', methods: ['GET'])]
    public function show(Event $event): Response
    {
        // Only show approved events to public, but allow admins to review pending events.
        if ($event->getStatus() !== Event::STATUS_APPROVED && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException('Event not found');
        }

        return $this->render('event/show.html.twig', [
            'event' => $event,
        ]);
    }
}
