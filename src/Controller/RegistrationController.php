<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Registration;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Repository\RegistrationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/registrations')]
final class RegistrationController extends AbstractController
{
    // List my registrations
    #[Route('', name: 'registration_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $registrations = $user->getRegistrations();

        return $this->render('registration/index.html.twig', [
            'registrations' => $registrations,
        ]);
    }

    // Register for an event (individual)
    #[Route('/event/{id}/register', name: 'registration_register', methods: ['POST'])]
    #[IsGranted('EVENT_REGISTER', subject: 'event')]
    public function register(Event $event, EntityManagerInterface $em, RegistrationRepository $registrationRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Check for duplicate active registration
        if ($registrationRepo->hasActiveRegistration($event, $user, Registration::activeStatuses())) {
            $this->addFlash('error', 'You are already registered for this event.');
            return $this->redirectToRoute('event_list');
        }

        // Check event capacity if set
        if ($event->getCapacity() !== null) {
            $currentRegistrations = count($event->getRegistrations()->filter(
                fn(Registration $r) => in_array($r->getStatus(), Registration::activeStatuses(), true)
            ));
            if ($currentRegistrations >= $event->getCapacity()) {
                $this->addFlash('error', 'Event is at full capacity.');
                return $this->redirectToRoute('event_list');
            }
        }

        // Only APPROVED events can be registered for
        if ($event->getStatus() !== Event::STATUS_APPROVED) {
            $this->addFlash('error', 'This event is not open for registration.');
            return $this->redirectToRoute('event_list');
        }

        $registration = new Registration();
        $registration->setEvent($event);
        $registration->setUser($user);
        $registration->setType(Registration::TYPE_INDIVIDUAL);
        $registration->setStatus(Registration::STATUS_CONFIRMED);
        $registration->generateQRCode();

        $em->persist($registration);
        $em->flush();

        $this->addFlash('success', 'Successfully registered for event. Your QR code has been generated.');
        return $this->redirectToRoute('registration_index');
    }

    // Register organization for an event
    #[Route('/event/{id}/register-org', name: 'registration_register_org', methods: ['POST'])]
    #[IsGranted('EVENT_REGISTER_ORG', subject: 'event')]
    public function registerOrganization(Event $event, Request $request, EntityManagerInterface $em, RegistrationRepository $registrationRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $organizationId = $request->request->get('organization');

        if (!$organizationId) {
            $this->addFlash('error', 'Please select an organization.');
            return $this->redirectToRoute('event_list');
        }

        // Find organization from user's organizations
        $organization = null;
        foreach ($user->getOrganizations() as $org) {
            if ($org->getId() == $organizationId) {
                $organization = $org;
                break;
            }
        }

        if (!$organization) {
            $this->addFlash('error', 'Invalid organization selected.');
            return $this->redirectToRoute('event_list');
        }

        // Check for duplicate organization registration
        if ($registrationRepo->hasActiveOrganizationRegistration($event, $organization, Registration::activeStatuses())) {
            $this->addFlash('error', 'This organization is already registered for this event.');
            return $this->redirectToRoute('event_list');
        }

        // Check event mode
        if ($event->getMode() !== Event::MODE_ORG) {
            $this->addFlash('error', 'This event does not support organization registration.');
            return $this->redirectToRoute('event_list');
        }

        // Only APPROVED events can be registered for
        if ($event->getStatus() !== Event::STATUS_APPROVED) {
            $this->addFlash('error', 'This event is not open for registration.');
            return $this->redirectToRoute('event_list');
        }

        $registration = new Registration();
        $registration->setEvent($event);
        $registration->setUser($user);
        $registration->setOrganization($organization);
        $registration->setType(Registration::TYPE_ORGANIZATION);
        $registration->setStatus(Registration::STATUS_CONFIRMED);
        $registration->generateQRCode();

        $em->persist($registration);
        $em->flush();

        $this->addFlash('success', 'Organization successfully registered for event.');
        return $this->redirectToRoute('registration_index');
    }

    // Cancel registration
    #[Route('/{id}/cancel', name: 'registration_cancel', methods: ['POST'])]
    #[IsGranted('REGISTRATION_CANCEL', subject: 'registration')]
    public function cancel(Registration $registration, EntityManagerInterface $em): Response
    {
        // Cannot cancel if already checked in
        if ($registration->getStatus() === Registration::STATUS_CHECKED_IN) {
            $this->addFlash('error', 'Cannot cancel - you have already checked in.');
            return $this->redirectToRoute('registration_index');
        }

        // Cannot cancel if event has already started
        if ($registration->getEvent()->getStartDate() < new \DateTimeImmutable()) {
            $this->addFlash('error', 'Cannot cancel - event has already started.');
            return $this->redirectToRoute('registration_index');
        }

        $registration->cancel();
        $em->flush();

        $this->addFlash('success', 'Registration cancelled successfully.');
        return $this->redirectToRoute('registration_index');
    }
}
