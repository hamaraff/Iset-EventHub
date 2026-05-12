<?php

namespace App\Security\Voter;

use App\Entity\Registration;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class RegistrationVoter extends Voter
{
    public const CANCEL = 'REGISTRATION_CANCEL';
    public const VIEW = 'REGISTRATION_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::CANCEL, self::VIEW], true) 
            && $subject instanceof Registration;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Registration $registration */
        $registration = $subject;

        return match($attribute) {
            self::CANCEL => $this->canCancel($registration, $user),
            self::VIEW => $this->canView($registration, $user),
            default => false,
        };
    }

    private function canCancel(Registration $registration, User $user): bool
    {
        // Only the registration owner can cancel
        return $registration->getUser() === $user;
    }

    private function canView(Registration $registration, User $user): bool
    {
        // Only the registration owner can view details
        return $registration->getUser() === $user;
    }
}
