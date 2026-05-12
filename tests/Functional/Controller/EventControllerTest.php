<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Event;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class EventControllerTest extends WebTestCase
{
    private $client;
    private $em;
    private $passwordHasher;
    
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
    }
    
    private function createUser(string $email, array $roles, string $password = 'password'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setName('Test User');
        $user->setRoles($roles);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        
        $this->em->persist($user);
        $this->em->flush();
        
        return $user;
    }
    
    private function createOrganization(string $name): Organization
    {
        $org = new Organization();
        $org->setName($name);
        $org->setDescription('Test Description');
        
        $this->em->persist($org);
        $this->em->flush();
        
        return $org;
    }
    
    private function createEvent(string $title, Organization $org, string $status = Event::STATUS_APPROVED): Event
    {
        $event = new Event();
        $event->setTitle($title);
        $event->setDescription('Test Description');
        $event->setStartDate(new \DateTimeImmutable('+1 day'));
        $event->setEndDate(new \DateTimeImmutable('+2 days'));
        $event->setLocation('Test Location');
        $event->setType(Event::TYPE_OPEN);
        $event->setMode(Event::MODE_INDIV);
        $event->setStatus($status);
        $event->setOrganization($org);
        
        $this->em->persist($event);
        $this->em->flush();
        
        return $event;
    }
    
    public function testPublicEventList(): void
    {
        $org = $this->createOrganization('Test Org');
        $event = $this->createEvent('Public Event', $org);
        
        $this->client->request('GET', '/events');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Events');
    }
    
    public function testPublicEventShow(): void
    {
        $org = $this->createOrganization('Test Org');
        $event = $this->createEvent('Show Event', $org);
        
        $this->client->request('GET', '/events/' . $event->getId());
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Show Event');
    }
    
    public function testOrganizerCanAccessEventManagement(): void
    {
        $org = $this->createOrganization('Org Org');
        $user = $this->createUser('org@example.com', ['ROLE_ORGANIZER']);
        $user->addOrganization($org);
        $this->em->flush();
        
        $this->client->loginUser($user);
        $this->client->request('GET', '/organizer/events');
        
        $this->assertResponseIsSuccessful();
    }
    
    public function testNonOrganizerCannotAccessEventManagement(): void
    {
        $user = $this->createUser('user@example.com', ['ROLE_PARTICIPANT']);
        
        $this->client->loginUser($user);
        $this->client->request('GET', '/organizer/events');
        
        $this->assertResponseStatusCodeSame(403);
    }
    
    public function testAdminCanAccessPendingEvents(): void
    {
        $user = $this->createUser('admin@example.com', ['ROLE_ADMIN']);
        
        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/events/pending');
        
        $this->assertResponseIsSuccessful();
    }
    
    protected function tearDown(): void
    {
        // Clean up
        $this->em->createQuery('DELETE FROM App\Entity\Registration')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Event')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Organization')->execute();
        
        parent::tearDown();
    }
}
