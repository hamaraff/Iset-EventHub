<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Organization;
use App\Entity\Registration;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testUserCreation(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setName('Test User');
        $user->setPassword('hashed_password');
        
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('Test User', $user->getName());
        $this->assertEquals('hashed_password', $user->getPassword());
    }
    
    public function testUserIdentifierIsEmail(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        
        $this->assertEquals('test@example.com', $user->getUserIdentifier());
    }
    
    public function testParticipantRoleIsAlwaysPresent(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ORGANIZER']);
        
        $roles = $user->getRoles();
        
        $this->assertContains('ROLE_PARTICIPANT', $roles);
        $this->assertContains('ROLE_ORGANIZER', $roles);
    }
    
    public function testParticipantRoleNotDuplicated(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_PARTICIPANT', 'ROLE_ADMIN']);
        
        $roles = $user->getRoles();
        
        // ROLE_PARTICIPANT should appear only once
        $participantCount = array_count_values($roles)['ROLE_PARTICIPANT'] ?? 0;
        $this->assertEquals(1, $participantCount);
    }
    
    public function testOrganizationMembership(): void
    {
        $user = new User();
        $org1 = new Organization();
        $org1->setName('Org 1');
        $org2 = new Organization();
        $org2->setName('Org 2');
        
        $this->assertCount(0, $user->getOrganizations());
        
        $user->addOrganization($org1);
        $this->assertCount(1, $user->getOrganizations());
        $this->assertTrue($user->getOrganizations()->contains($org1));
        
        $user->addOrganization($org2);
        $this->assertCount(2, $user->getOrganizations());
        
        $user->removeOrganization($org1);
        $this->assertCount(1, $user->getOrganizations());
        $this->assertFalse($user->getOrganizations()->contains($org1));
        $this->assertTrue($user->getOrganizations()->contains($org2));
    }
    
    public function testRegistrationRelation(): void
    {
        $user = new User();
        $registration = new Registration();
        $registration->setType(Registration::TYPE_INDIVIDUAL);
        
        $this->assertCount(0, $user->getRegistrations());
        
        $user->addRegistration($registration);
        $this->assertCount(1, $user->getRegistrations());
        $this->assertSame($user, $registration->getUser());
        
        $user->removeRegistration($registration);
        $this->assertCount(0, $user->getRegistrations());
    }
    
    public function testIsMemberOfOrganization(): void
    {
        $user = new User();
        $org = new Organization();
        $org->setName('Test Org');
        
        $this->assertFalse($user->isMemberOf($org));
        
        $user->addOrganization($org);
        $this->assertTrue($user->isMemberOf($org));
    }
    
    public function testEraseCredentialsDoesNotRemovePassword(): void
    {
        $user = new User();
        $user->setPassword('hashed_password');
        
        // eraseCredentials is for sensitive temporary data, not persistent passwords
        $user->eraseCredentials();
        
        $this->assertEquals('hashed_password', $user->getPassword());
    }
}
