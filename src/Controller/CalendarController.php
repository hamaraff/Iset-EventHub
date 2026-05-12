<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/calendar')]
final class CalendarController extends AbstractController
{
    #[Route('', name: 'calendar_index', methods: ['GET'])]
    public function index(Request $request, EventRepository $eventRepo): Response
    {
        $year = (int) $request->query->get('year', date('Y'));
        $month = (int) $request->query->get('month', date('n'));
        $filterType = $request->query->get('type');
        $filterStatus = $request->query->get('status');
        
        // Normalize month/year
        if ($month < 1) {
            $month = 12;
            $year--;
        } elseif ($month > 12) {
            $month = 1;
            $year++;
        }
        
        $startOfMonth = new \DateTimeImmutable("$year-$month-01");
        $endOfMonth = $startOfMonth->modify('last day of this month')->setTime(23, 59, 59);
        
        // Get events for the month
        $events = $eventRepo->findForCalendar($startOfMonth, $endOfMonth, $filterType, $filterStatus);
        
        // Build calendar grid
        $calendar = $this->buildCalendarGrid($startOfMonth, $events);
        
        // Get upcoming events (next 30 days)
        $upcoming = $eventRepo->findUpcomingApproved(30);
        
        return $this->render('calendar/index.html.twig', [
            'calendar' => $calendar,
            'year' => $year,
            'month' => $month,
            'monthName' => $startOfMonth->format('F'),
            'filterType' => $filterType,
            'filterStatus' => $filterStatus,
            'upcoming' => $upcoming,
            'prevMonth' => $month - 1 < 1 ? 12 : $month - 1,
            'prevYear' => $month - 1 < 1 ? $year - 1 : $year,
            'nextMonth' => $month + 1 > 12 ? 1 : $month + 1,
            'nextYear' => $month + 1 > 12 ? $year + 1 : $year,
            'eventTypes' => Event::TYPE_LABELS,
            'eventStatuses' => [
                Event::STATUS_DRAFT => 'Draft',
                Event::STATUS_PENDING => 'Pending',
                Event::STATUS_APPROVED => 'Approved',
                Event::STATUS_REFUSED => 'Refused',
            ],
        ]);
    }
    
    #[Route('/list', name: 'calendar_list', methods: ['GET'])]
    public function list(Request $request, EventRepository $eventRepo): Response
    {
        $filterType = $request->query->get('type');
        $filterStatus = $request->query->get('status', Event::STATUS_APPROVED);
        $dateFrom = $request->query->get('from');
        $dateTo = $request->query->get('to');
        
        $from = $dateFrom ? new \DateTimeImmutable($dateFrom) : new \DateTimeImmutable('today');
        $to = $dateTo ? new \DateTimeImmutable($dateTo . ' 23:59:59') : $from->modify('+90 days');
        
        $events = $eventRepo->findFiltered($from, $to, $filterType, $filterStatus);
        
        return $this->render('calendar/list.html.twig', [
            'events' => $events,
            'filterType' => $filterType,
            'filterStatus' => $filterStatus,
            'dateFrom' => $from->format('Y-m-d'),
            'dateTo' => $to->format('Y-m-d'),
            'eventTypes' => Event::TYPE_LABELS,
            'eventStatuses' => [
                Event::STATUS_DRAFT => 'Draft',
                Event::STATUS_PENDING => 'Pending',
                Event::STATUS_APPROVED => 'Approved',
                Event::STATUS_REFUSED => 'Refused',
            ],
        ]);
    }
    
    private function buildCalendarGrid(\DateTimeImmutable $startOfMonth, array $events): array
    {
        $grid = [];
        $firstDayOfWeek = (int) $startOfMonth->format('N'); // 1 = Monday
        $daysInMonth = (int) $startOfMonth->format('t');
        
        // Empty cells for days before month starts
        for ($i = 1; $i < $firstDayOfWeek; $i++) {
            $grid[] = ['day' => null, 'events' => [], 'isToday' => false];
        }
        
        // Days of month
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $startOfMonth->setDate((int) $startOfMonth->format('Y'), (int) $startOfMonth->format('n'), $day);
            $dayEvents = [];
            
            foreach ($events as $event) {
                // Event spans this day
                if ($date >= $event->getStartDate()->setTime(0, 0) && 
                    $date <= $event->getEndDate()->setTime(23, 59, 59)) {
                    $dayEvents[] = $event;
                }
            }
            
            $grid[] = [
                'day' => $day,
                'date' => $date,
                'events' => $dayEvents,
                'isToday' => $date->format('Y-m-d') === date('Y-m-d'),
            ];
        }
        
        return $grid;
    }
}
