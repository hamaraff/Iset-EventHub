<?php

namespace App\Security\Voter;

use App\Entity\Event;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class EventVoter extends Voter
{
    public const EDIT='EVENT_EDIT';
    public const DELETE='EVENT_DELETE';
    public const SUBMIT='EVENT_SUBMIT';

    public function __construct(private Security $security){}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute,[self::EDIT,self::DELETE,self::SUBMIT],true)&& $subject instanceof Event;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if(!$user instanceof User){
            return false;

        }
        /** @var Event $event */
        $event = $subject;
        return match($attribute){
            self::EDIT=>$this->canEdit($event,$user),
            self::DELETE=>$this->canDelete($event,$user),
            self::SUBMIT=>$this->canSubmit($event,$user),
            default=>false,
        };
    }

    private function canEdit(Event $event, User $user):bool
    {
        $org = $event->getOrganization();

        if(!$org){
            return false;
        }
        return $org->getMembers()->contains($user);
    }

    private function canDelete(Event $event,User $user):bool
    {
        if($event->getStatus()!==Event::STATUS_DRAFT){
            return false;
        }
        return $this->canEdit($event,$user);
    }

    private function canSubmit(Event $event , User $user):bool 
    {
        if($event->getStatus()!==Event::STATUS_DRAFT){
            return false;
        }
        return $this->canEdit($event,$user);
    }
}
