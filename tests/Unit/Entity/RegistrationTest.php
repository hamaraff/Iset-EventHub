<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Event;
use App\Entity\Registration;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class RegistrationTest extends TestCase
{
    public function testRegistrationCreation(): void
    {
        $registration = new Registration();
        $user = new User();
        $user->setEmail('test@example.com');
        $event = new Event();
        $event->setTitle('Test Event');
        
        $registration->setUser($user);
        $registration->setEvent($event);
        $registration->setType(Registration::TYPE_INDIVIDUAL);
        $registration->setStatus(Registration::STATUS_PENDING);
        
        $this->assertSame($user, $registration->getUser());
        $this->assertSame($event, $registration->getEvent());
        $this->assertEquals(Registration::TYPE_INDIVIDUAL, $registration->getType());
        $this->assertEquals(Registration::STATUS_PENDING, $registration->getStatus());
    }
    
    public function testQrCodeGeneration(): void
    {
        $registration = new Registration();
        
        $this->assertNull($registration->getQrCode());
        
        $registration->generateQrCode();
        
        $this->assertNotNull($registration->getQrCode());
        $this->assertEquals(32, strlen($registration->getQrCode())); // MD5 hash length
        $this->assertTrue(ctype_xdigit($registration->getQrCode())); // Should be hexadecimal
    }
    
    public function testCheckInProcess(): void
    {
        $registration = new Registration();
        $registration->setStatus(Registration::STATUS_CONFIRMED);
        
        $this->assertNull($registration->getCheckedInAt());
        $this->assertFalse($registration->isCheckedIn());
        
        $registration->checkIn();
        
        $this->assertEquals(Registration::STATUS_CHECKED_IN, $registration->getStatus());
        $this->assertNotNull($registration->getCheckedInAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $registration->getCheckedInAt());
        $this->assertTrue($registration->isCheckedIn());
    }
    
    public function testCannotCheckInIfNotConfirmed(): void
    {
        $registration = new Registration();
        $registration->setStatus(Registration::STATUS_PENDING);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only confirmed registrations can be checked in');
        
        $registration->checkIn();
    }
    
    public function testCannotCheckInIfAlreadyCheckedIn(): void
    {
        $registration = new Registration();
        $registration->setStatus(Registration::STATUS_CONFIRMED);
        $registration->checkIn();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Already checked in');
        
        $registration->checkIn();
    }
    
    public function testCannotCheckInIfCancelled(): void
    {
        $registration = new Registration();
        $registration->setStatus(Registration::STATUS_CANCELLED);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot check in cancelled registration');
        
        $registration->checkIn();
    }
    
    public function testCancelRegistration(): void
    {
        $registration = new Registration();
        $registration->setStatus(Registration::STATUS_PENDING);
        
        $this->assertTrue($registration->canBeCancelled());
        
        $registration->cancel();
        
        $this->assertEquals(Registration::STATUS_CANCELLED, $registration->getStatus());
        $this->assertFalse($registration->canBeCancelled());
    }
    
    public function testCannotCancelAlreadyCancelled(): void
    {
        $registration = new Registration();
        $registration->setStatus(Registration::STATUS_CANCELLED);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Registration already cancelled');
        
        $registration->cancel();
    }
    
    public function testCannotCancelCheckedIn(): void
    {
        $registration = new Registration();
        $registration->setStatus(Registration::STATUS_CONFIRMED);
        $registration->checkIn();
        
        $this->assertFalse($registration->canBeCancelled());
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot cancel checked-in registration');
        
        $registration->cancel();
    }
    
    public function testRegistrationConstants(): void
    {
        $this->assertEquals('PENDING', Registration::STATUS_PENDING);
        $this->assertEquals('CONFIRMED', Registration::STATUS_CONFIRMED);
        $this->assertEquals('CANCELLED', Registration::STATUS_CANCELLED);
        $this->assertEquals('CHECKED_IN', Registration::STATUS_CHECKED_IN);
        $this->assertEquals('INDIVIDUAL', Registration::TYPE_INDIVIDUAL);
        $this->assertEquals('ORGANIZATION', Registration::TYPE_ORGANIZATION);
    }
}
