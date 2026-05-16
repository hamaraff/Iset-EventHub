<?php

namespace App\Controller\Staff;

use App\Entity\Event;
use App\Entity\Registration;
use App\Repository\EventRepository;
use App\Repository\RegistrationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/staff')]
#[IsGranted('ROLE_STAFF')]
final class QrScanController extends AbstractController
{
    // Staff scan interface
    #[Route('/scan', name: 'staff_scan', methods: ['GET'])]
    public function scan(): Response
    {
        return $this->render('staff/scan.html.twig');
    }

    // Validate QR code and return registration info
    #[Route('/scan/validate', name: 'staff_scan_validate', methods: ['POST'])]
    public function validateQr(Request $request, RegistrationRepository $registrationRepo): Response
    {
        $qrCode = $request->request->get('qr_code');
        
        if (!$qrCode) {
            return $this->json(['valid' => false, 'message' => 'QR code required'], 400);
        }

        $registration = $registrationRepo->findOneBy(['qrCode' => $qrCode]);

        if (!$registration) {
            return $this->json(['valid' => false, 'message' => 'Invalid QR code'], 404);
        }

        $event = $registration->getEvent();

        // Restrict staff to their own organizations' events (admins bypass)
        $user = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN')) {
            $org = $event->getOrganization();
            if ($org && !$user->getOrganizations()->contains($org)) {
                return $this->json(['valid' => false, 'message' => 'You are not permitted to validate tickets for this event'], 403);
            }
        }
        // Check if already checked in
        if ($registration->getStatus() === Registration::STATUS_CHECKED_IN) {
            return $this->json([
                'valid' => false,
                'message' => 'Already checked in',
                'checkedInAt' => $registration->getCheckedInAt()->format('Y-m-d H:i:s'),
                'participant' => $registration->getUser()->getName(),
                'event' => $event->getTitle()
            ], 400);
        }

        // Check if cancelled
        if ($registration->getStatus() === Registration::STATUS_CANCELLED) {
            return $this->json([
                'valid' => false,
                'message' => 'Registration cancelled',
                'participant' => $registration->getUser()->getName(),
                'event' => $event->getTitle()
            ], 400);
        }

        return $this->json([
            'valid' => true,
            'canCheckIn' => true,
            'message' => 'QR code validated. Attendee can be checked in.',
            'registrationId' => $registration->getId(),
            'participant' => $registration->getUser()->getName(),
            'email' => $registration->getUser()->getEmail(),
            'event' => $event->getTitle(),
            'start' => $event->getStartDate()->format('Y-m-d H:i'),
            'end' => $event->getEndDate()->format('Y-m-d H:i'),
            'type' => $registration->getType(),
            'organization' => $registration->getOrganization() ? $registration->getOrganization()->getName() : null,
            'status' => $registration->getStatus()
        ]);
    }

    // Mark attendance (check-in)
    #[Route('/scan/checkin', name: 'staff_scan_checkin', methods: ['POST'])]
    public function checkIn(Request $request, RegistrationRepository $registrationRepo, EntityManagerInterface $em): Response
    {
        $registrationId = $request->request->get('registration_id');
        
        if (!$registrationId) {
            return $this->json(['success' => false, 'message' => 'Registration ID required'], 400);
        }

        $registration = $registrationRepo->find($registrationId);

        if (!$registration) {
            return $this->json(['success' => false, 'message' => 'Registration not found'], 404);
        }

        $event = $registration->getEvent();

        // Restrict staff to their own organizations' events (admins bypass)
        $user = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN')) {
            $org = $event->getOrganization();
            if ($org && !$user->getOrganizations()->contains($org)) {
                return $this->json(['success' => false, 'message' => 'You are not permitted to check in attendees for this event'], 403);
            }
        }

        // Verify registration is valid for check-in
        if ($registration->getStatus() === Registration::STATUS_CHECKED_IN) {
            return $this->json([
                'success' => false, 
                'message' => 'Already checked in',
                'checkedInAt' => $registration->getCheckedInAt()->format('Y-m-d H:i:s')
            ], 400);
        }

        if ($registration->getStatus() === Registration::STATUS_CANCELLED) {
            return $this->json(['success' => false, 'message' => 'Registration cancelled'], 400);
        }

        // Perform check-in
        $registration->checkIn();
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Check-in successful',
            'participant' => $registration->getUser()->getName(),
            'event' => $event->getTitle(),
            'checkedInAt' => $registration->getCheckedInAt()->format('Y-m-d H:i:s')
        ]);
    }

    // List checked-in participants for an event
    #[Route('/event/{id}/attendance', name: 'staff_event_attendance', methods: ['GET'])]
    public function attendance(Event $event): Response
    {
        // Restrict staff to their own organizations' events (admins bypass)
        $user = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN')) {
            $org = $event->getOrganization();
            if ($org && !$user->getOrganizations()->contains($org)) {
                $this->addFlash('error', 'You are not permitted to view attendance for this event.');
                return $this->redirectToRoute('staff_scan');
            }
        }
        $checkedIn = $event->getRegistrations()->filter(
            fn(Registration $r) => $r->getStatus() === Registration::STATUS_CHECKED_IN
        );

        $confirmed = $event->getRegistrations()->filter(
            fn(Registration $r) => $r->getStatus() === Registration::STATUS_CONFIRMED
        );

        return $this->render('staff/attendance.html.twig', [
            'event' => $event,
            'checkedIn' => $checkedIn,
            'confirmed' => $confirmed,
            'totalRegistered' => count($event->getRegistrations()),
            'totalCheckedIn' => count($checkedIn)
        ]);
    }
}
