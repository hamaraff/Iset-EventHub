<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class StaffVoter extends Voter
{
    public const SCAN = 'STAFF_SCAN';
    public const VIEW_ATTENDANCE = 'STAFF_VIEW_ATTENDANCE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::SCAN, self::VIEW_ATTENDANCE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // Only STAFF or ADMIN can scan and view attendance
        return in_array('ROLE_STAFF', $user->getRoles(), true) 
            || in_array('ROLE_ADMIN', $user->getRoles(), true);
    }
}
