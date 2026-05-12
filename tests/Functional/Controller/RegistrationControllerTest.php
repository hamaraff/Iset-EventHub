<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Event;
use App\Entity\Organization;
use App\Entity\Registration;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationControllerTest extends WebTestCase
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
    
    private function createApprovedEvent(): Event
    {
        $org = new Organization();
        $org->setName('Test Org');
        $org->setDescription('Description');
        $this->em->persist($org);
        
        $event = new Event();
        $event->setTitle('Test Event');
        $event->setDescription('Test Description');
        $event->setStartDate(new \DateTimeImmutable('+1 day'));
        $event->setEndDate(new \DateTimeImmutable('+2 days'));
        $event->setLocation('Test Location');
        $event->setType(Event::TYPE_OPEN);
        $event->setMode(Event::MODE_INDIV);
        $event->setStatus(Event::STATUS_APPROVED);
        $event->setOrganization($org);
        
        $this->em->persist($event);
        $this->em->flush();
        
        return $event;
    }
    
    public function testUserCanViewRegistrations(): void
    {
        $user = $this->createUser('user@example.com', ['ROLE_PARTICIPANT']);
        
        $this->client->loginUser($user);
        $this->client->request('GET', '/registrations');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'My Registrations');
    }
    
    public function testAnonymousUserCannotViewRegistrations(): void
    {
        $this->client->request('GET', '/registrations');
        
        $this->assertResponseRedirects('/login');
    }
    
    public function testUserCanRegisterForEvent(): void
    {
        $user = $this->createUser('user@example.com', ['ROLE_PARTICIPANT']);
        $event = $this->createApprovedEvent();
        
        $this->client->loginUser($user);
        $this->client->request('POST', '/event/' . $event->getId() . '/register');
        
        $this->assertResponseRedirects();
        
        // Verify registration was created
        $registration = $this->em->getRepository(Registration::class)->findOneBy([
            'user' => $user,
            'event' => $event,
        ]);
        
        $this->assertNotNull($registration);
        $this->assertEquals(Registration::STATUS_PENDING, $registration->getStatus());
    }
    
    public function testCannotRegisterTwiceForSameEvent(): void
    {
        $user = $this->createUser('user@example.com', ['ROLE_PARTICIPANT']);
        $event = $this->createApprovedEvent();
        
        // First registration
        $reg = new Registration();
        $reg->setUser($user);
        $reg->setEvent($event);
        $reg->setType(Registration::TYPE_INDIVIDUAL);
        $reg->setStatus(Registration::STATUS_PENDING);
        $this->em->persist($reg);
        $this->em->flush();
        
        $this->client->loginUser($user);
        $this->client->request('POST', '/event/' . $event->getId() . '/register');
        
        $this->assertResponseRedirects();
        
        // Should have only one registration
        $count = $this->em->getRepository(Registration::class)->count([
            'user' => $user,
            'event' => $event,
        ]);
        
        $this->assertEquals(1, $count);
    }
    
    public function testUserCanCancelRegistration(): void
    {
        $user = $this->createUser('user@example.com', ['ROLE_PARTICIPANT']);
        $event = $this->createApprovedEvent();
        
        $reg = new Registration();
        $reg->setUser($user);
        $reg->setEvent($event);
        $reg->setType(Registration::TYPE_INDIVIDUAL);
        $reg->setStatus(Registration::STATUS_CONFIRMED);
        $this->em->persist($reg);
        $this->em->flush();
        
        $this->client->loginUser($user);
        $this->client->request('POST', '/registration/' . $reg->getId() . '/cancel');
        
        $this->assertResponseRedirects('/registrations');
        
        $this->em->refresh($reg);
        $this->assertEquals(Registration::STATUS_CANCELLED, $reg->getStatus());
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
