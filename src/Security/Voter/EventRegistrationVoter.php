<?php

namespace App\Security\Voter;

use App\Entity\Event;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class EventRegistrationVoter extends Voter
{
    public const REGISTER = 'EVENT_REGISTER';
    public const REGISTER_ORG = 'EVENT_REGISTER_ORG';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::REGISTER, self::REGISTER_ORG], true) 
            && $subject instanceof Event;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Event $event */
        $event = $subject;

        // Event must be approved to register
        if ($event->getStatus() !== Event::STATUS_APPROVED) {
            return false;
        }

        return match($attribute) {
            self::REGISTER => $this->canRegister($event, $user),
            self::REGISTER_ORG => $this->canRegisterOrganization($event, $user),
            default => false,
        };
    }

    private function canRegister(Event $event, User $user): bool
    {
        // Individual registration allowed only for INDIVIDUEL mode events
        if ($event->getMode() !== Event::MODE_INDIV) {
            return false;
        }

        // User must be logged in (ROLE_PARTICIPANT minimum)
        return in_array('ROLE_PARTICIPANT', $user->getRoles(), true);
    }

    private function canRegisterOrganization(Event $event, User $user): bool
    {
        // Organization registration only for ORG mode events
        if ($event->getMode() !== Event::MODE_ORG) {
            return false;
        }

        // User must belong to at least one organization
        return !$user->getOrganizations()->isEmpty();
    }
}
