<?php

namespace App\Security\Voter;

use App\Entity\Organization;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class OrganizationVoter extends Voter
{
    public const EDIT = 'ORGANIZATION_EDIT';
    public const DELETE = 'ORGANIZATION_DELETE';
    public const MANAGE_MEMBERS = 'ORGANIZATION_MANAGE_MEMBERS';
    public const VIEW = 'ORGANIZATION_VIEW';

    public function __construct(private Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE, self::MANAGE_MEMBERS, self::VIEW], true)
            && $subject instanceof Organization;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Organization $organization */
        $organization = $subject;

        return match ($attribute) {
            self::EDIT => $this->canEdit($organization, $user),
            self::DELETE => $this->canDelete($organization, $user),
            self::MANAGE_MEMBERS => $this->canManageMembers($organization, $user),
            self::VIEW => $this->canView($organization, $user),
            default => false,
        };
    }

    private function canEdit(Organization $organization, User $user): bool
    {
        // Admins can edit any organization
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Organization members can edit their own organization
        return $organization->getMembers()->contains($user);
    }

    private function canDelete(Organization $organization, User $user): bool
    {
        // Only admins can delete organizations
        return $this->security->isGranted('ROLE_ADMIN');
    }

    private function canManageMembers(Organization $organization, User $user): bool
    {
        // Admins can manage members of any organization
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Organization members can manage their own organization's members
        return $organization->getMembers()->contains($user);
    }

    private function canView(Organization $organization, User $user): bool
    {
        // Admins can view any organization
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Organization members can view their own organization
        return $organization->getMembers()->contains($user);
    }
}
