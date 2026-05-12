<?php

namespace App\Tests\Security;

use App\Entity\Event;
use App\Entity\Organization;
use App\Entity\Registration;
use App\Entity\User;
use App\Security\Voter\EventVoter;
use App\Security\Voter\RegistrationVoter;
use App\Security\Voter\StaffVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class AccessControlTest extends WebTestCase
{
    private $em;
    private $passwordHasher;
    private $security;
    
    protected function setUp(): void
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->security = static::getContainer()->get('security.helper');
    }
    
    private function createUser(string $email, array $roles): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setName('Test User');
        $user->setRoles($roles);
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'password');
        $user->setPassword($hashedPassword);
        
        $this->em->persist($user);
        $this->em->flush();
        
        return $user;
    }
    
    private function createOrganization(string $name): Organization
    {
        $org = new Organization();
        $org->setName($name);
        $org->setDescription('Description');
        
        $this->em->persist($org);
        $this->em->flush();
        
        return $org;
    }
    
    private function createEvent(Organization $org, string $status = Event::STATUS_DRAFT): Event
    {
        $event = new Event();
        $event->setTitle('Test Event');
        $event->setDescription('Description');
        $event->setStartDate(new \DateTimeImmutable('+1 day'));
        $event->setEndDate(new \DateTimeImmutable('+2 days'));
        $event->setLocation('Location');
        $event->setType(Event::TYPE_OPEN);
        $event->setMode(Event::MODE_INDIV);
        $event->setStatus($status);
        $event->setOrganization($org);
        
        $this->em->persist($event);
        $this->em->flush();
        
        return $event;
    }
    
    public function testEventVoterEditPermission(): void
    {
        $org = $this->createOrganization('Test Org');
        $owner = $this->createUser('owner@example.com', ['ROLE_ORGANIZER']);
        $owner->addOrganization($org);
        
        $otherUser = $this->createUser('other@example.com', ['ROLE_ORGANIZER']);
        
        $event = $this->createEvent($org, Event::STATUS_DRAFT);
        
        // Owner can edit draft event
        $token = new UsernamePasswordToken($owner, 'main', $owner->getRoles());
        $voter = new EventVoter();
        $this->assertEquals(1, $voter->vote($token, $event, [EventVoter::EDIT]));
        
        // Other user cannot edit
        $token2 = new UsernamePasswordToken($otherUser, 'main', $otherUser->getRoles());
        $this->assertEquals(-1, $voter->vote($token2, $event, [EventVoter::EDIT]));
    }
    
    public function testEventVoterDeletePermission(): void
    {
        $org = $this->createOrganization('Test Org');
        $owner = $this->createUser('owner@example.com', ['ROLE_ORGANIZER']);
        $owner->addOrganization($org);
        
        $draftEvent = $this->createEvent($org, Event::STATUS_DRAFT);
        $approvedEvent = $this->createEvent($org, Event::STATUS_APPROVED);
        
        $token = new UsernamePasswordToken($owner, 'main', $owner->getRoles());
        $voter = new EventVoter();
        
        // Can delete draft
        $this->assertEquals(1, $voter->vote($token, $draftEvent, [EventVoter::DELETE]));
        
        // Cannot delete approved
        $this->assertEquals(-1, $voter->vote($token, $approvedEvent, [EventVoter::DELETE]));
    }
    
    public function testEventVoterSubmitPermission(): void
    {
        $org = $this->createOrganization('Test Org');
        $owner = $this->createUser('owner@example.com', ['ROLE_ORGANIZER']);
        $owner->addOrganization($org);
        
        $draftEvent = $this->createEvent($org, Event::STATUS_DRAFT);
        
        $token = new UsernamePasswordToken($owner, 'main', $owner->getRoles());
        $voter = new EventVoter();
        
        // Can submit draft
        $this->assertEquals(1, $voter->vote($token, $draftEvent, [EventVoter::SUBMIT]));
    }
    
    public function testRegistrationVoterViewPermission(): void
    {
        $user = $this->createUser('user@example.com', ['ROLE_PARTICIPANT']);
        $otherUser = $this->createUser('other@example.com', ['ROLE_PARTICIPANT']);
        
        $org = $this->createOrganization('Test Org');
        $event = $this->createEvent($org, Event::STATUS_APPROVED);
        
        $registration = new Registration();
        $registration->setUser($user);
        $registration->setEvent($event);
        $registration->setType(Registration::TYPE_INDIVIDUAL);
        $registration->setStatus(Registration::STATUS_PENDING);
        
        $this->em->persist($registration);
        $this->em->flush();
        
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $voter = new RegistrationVoter();
        
        // Owner can view
        $this->assertEquals(1, $voter->vote($token, $registration, [RegistrationVoter::VIEW]));
        
        // Other user cannot view
        $token2 = new UsernamePasswordToken($otherUser, 'main', $otherUser->getRoles());
        $this->assertEquals(-1, $voter->vote($token2, $registration, [RegistrationVoter::VIEW]));
    }
    
    public function testRegistrationVoterCancelPermission(): void
    {
        $user = $this->createUser('user@example.com', ['ROLE_PARTICIPANT']);
        
        $org = $this->createOrganization('Test Org');
        $event = $this->createEvent($org, Event::STATUS_APPROVED);
        
        $registration = new Registration();
        $registration->setUser($user);
        $registration->setEvent($event);
        $registration->setType(Registration::TYPE_INDIVIDUAL);
        $registration->setStatus(Registration::STATUS_CONFIRMED);
        
        $this->em->persist($registration);
        $this->em->flush();
        
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $voter = new RegistrationVoter();
        
        // Can cancel confirmed registration
        $this->assertEquals(1, $voter->vote($token, $registration, [RegistrationVoter::CANCEL]));
    }
    
    public function testStaffVoterPermission(): void
    {
        $staff = $this->createUser('staff@example.com', ['ROLE_STAFF']);
        $admin = $this->createUser('admin@example.com', ['ROLE_ADMIN']);
        $participant = $this->createUser('user@example.com', ['ROLE_PARTICIPANT']);
        
        $voter = new StaffVoter();
        
        // Staff can scan
        $token1 = new UsernamePasswordToken($staff, 'main', $staff->getRoles());
        $this->assertEquals(1, $voter->vote($token1, null, [StaffVoter::SCAN]));
        
        // Admin can scan
        $token2 = new UsernamePasswordToken($admin, 'main', $admin->getRoles());
        $this->assertEquals(1, $voter->vote($token2, null, [StaffVoter::SCAN]));
        
        // Participant cannot scan
        $token3 = new UsernamePasswordToken($participant, 'main', $participant->getRoles());
        $this->assertEquals(-1, $voter->vote($token3, null, [StaffVoter::SCAN]));
    }
    
    protected function tearDown(): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\Registration')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Event')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Organization')->execute();
        
        parent::tearDown();
    }
}
