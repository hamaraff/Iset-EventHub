<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Organization;
use App\Entity\Registration;
use App\Entity\User;
use App\Repository\RegistrationRepository;
use Doctrine\ORM\EntityManagerInterface;

class RegistrationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RegistrationRepository $registrationRepository,
    ) {
    }

    public function registerIndividual(Event $event, User $user): Registration
    {
        if ($this->registrationRepository->hasActiveRegistration(
            $event,
            $user,
            Registration::activeStatuses()
        )) {
            throw new \RuntimeException('User already has an active registration for this event.');
        }

        return $this->em->wrapInTransaction(function () use ($event, $user): Registration {
            $registration = new Registration();
            $registration->setEvent($event);
            $registration->setUser($user);
            $registration->setType(Registration::TYPE_INDIVIDUAL);

            $this->em->persist($registration);

            return $registration;
        });
    }

    public function registerOrganization(Event $event, User $representative, Organization $organization): Registration
    {
        if (!$representative->getOrganizations()->contains($organization)) {
            throw new \RuntimeException('Representative is not a member of this organization.');
        }

        if ($this->registrationRepository->hasActiveRegistration(
            $event,
            $representative,
            Registration::activeStatuses()
        )) {
            throw new \RuntimeException('User already has an active registration for this event.');
        }

        if ($this->registrationRepository->hasActiveOrganizationRegistration(
            $event,
            $organization,
            Registration::activeStatuses()
        )) {
            throw new \RuntimeException('Organization already has an active registration for this event.');
        }

        return $this->em->wrapInTransaction(function () use ($event, $representative, $organization): Registration {
            $registration = new Registration();
            $registration->setEvent($event);
            $registration->setUser($representative);
            $registration->setOrganization($organization);
            $registration->setType(Registration::TYPE_ORGANIZATION);

            $this->em->persist($registration);

            return $registration;
        });
    }
}