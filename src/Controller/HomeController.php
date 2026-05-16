<?php

namespace App\Controller;

use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home_index', methods: ['GET'])]
    public function index(EventRepository $eventRepository): Response
    {
        $upcomingEvents = $eventRepository->findUpcomingApproved(30);
        $featuredEvents = array_slice($upcomingEvents, 0, 3);
        $calendarPreview = $this->buildCalendarPreview($upcomingEvents);

        return $this->render('home/index.html.twig', [
            'featuredEvents' => $featuredEvents,
            'calendarPreview' => $calendarPreview,
            'upcomingCount' => count($upcomingEvents),
        ]);
    }

    private function buildCalendarPreview(array $events): array
    {
        $today = new \DateTimeImmutable('today');
        $preview = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $today->modify(sprintf('+%d days', $i));
            $dayEvents = [];

            foreach ($events as $event) {
                $start = $event->getStartDate()->setTime(0, 0);
                $end = $event->getEndDate()->setTime(23, 59, 59);
                if ($day >= $start && $day <= $end) {
                    $dayEvents[] = $event;
                }
            }

            $preview[] = [
                'label' => $day->format('D'),
                'day' => $day->format('j'),
                'count' => count($dayEvents),
                'isToday' => $day->format('Y-m-d') === (new \DateTimeImmutable('today'))->format('Y-m-d'),
            ];
        }

        return $preview;
    }
}
