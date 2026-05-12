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
        $events = $eventRepository->findBy(
            ['status' => Event::STATUS_APPROVED],
            ['startDate' => 'ASC']
        );

        return $this->render('event/index.html.twig', [
            'events' => $events,
        ]);
    }

    // View single event details
    #[Route('/{id}', name: 'event_show', methods: ['GET'])]
    public function show(Event $event): Response
    {
        // Only show approved events to public
        if ($event->getStatus() !== Event::STATUS_APPROVED) {
            throw $this->createNotFoundException('Event not found');
        }

        return $this->render('event/show.html.twig', [
            'event' => $event,
        ]);
    }
}
