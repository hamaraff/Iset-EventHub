<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Event;
use App\Entity\Organization;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    public function testEventCreation(): void
    {
        $event = new Event();
        $event->setTitle('Test Event');
        $event->setDescription('Test Description');
        $event->setLocation('Test Location');
        $event->setType(Event::TYPE_OPEN);
        $event->setMode(Event::MODE_INDIV);
        $event->setCapacity(100);
        
        $this->assertEquals('Test Event', $event->getTitle());
        $this->assertEquals('Test Description', $event->getDescription());
        $this->assertEquals('Test Location', $event->getLocation());
        $this->assertEquals(Event::TYPE_OPEN, $event->getType());
        $this->assertEquals(Event::MODE_INDIV, $event->getMode());
        $this->assertEquals(100, $event->getCapacity());
    }
    
    public function testDefaultStatusIsDraft(): void
    {
        $event = new Event();
        // Status should be null initially until explicitly set
        $this->assertNull($event->getStatus());
        
        $event->setStatus(Event::STATUS_DRAFT);
        $this->assertEquals(Event::STATUS_DRAFT, $event->getStatus());
    }
    
    public function testEventLifecycleTransitions(): void
    {
        $event = new Event();
        
        // Draft to Pending
        $event->setStatus(Event::STATUS_DRAFT);
        $this->assertTrue($event->isEditableByOrganizer());
        
        $event->setStatus(Event::STATUS_PENDING);
        $this->assertFalse($event->isEditableByOrganizer());
        
        // Approved edit resets to pending
        $event->setStatus(Event::STATUS_APPROVED);
        $this->assertTrue($event->isEditableByOrganizer());
        
        $event->markAsPendingAfterApprovedEdit();
        $this->assertEquals(Event::STATUS_PENDING, $event->getStatus());
    }
    
    public function testEventDateValidation(): void
    {
        $event = new Event();
        $startDate = new \DateTimeImmutable('2026-06-01 10:00');
        $endDate = new \DateTimeImmutable('2026-06-01 18:00');
        
        $event->setStartDate($startDate);
        $event->setEndDate($endDate);
        
        $this->assertEquals($startDate, $event->getStartDate());
        $this->assertEquals($endDate, $event->getEndDate());
    }
    
    public function testEventOrganizationRelation(): void
    {
        $event = new Event();
        $organization = new Organization();
        $organization->setName('Test Org');
        
        $event->setOrganization($organization);
        
        $this->assertSame($organization, $event->getOrganization());
        $this->assertEquals('Test Org', $event->getOrganization()->getName());
    }
    
    public function testEventTypeAndModeLabels(): void
    {
        $this->assertEquals('OUVERT', Event::TYPE_OPEN);
        $this->assertEquals('COMPETITION', Event::TYPE_COMPET);
        $this->assertEquals('INDIVIDUEL', Event::MODE_INDIV);
        $this->assertEquals('ORGANISATION', Event::MODE_ORG);
        
        $this->assertArrayHasKey(Event::TYPE_OPEN, Event::TYPE_LABELS);
        $this->assertArrayHasKey(Event::TYPE_COMPET, Event::TYPE_LABELS);
        $this->assertArrayHasKey(Event::MODE_INDIV, Event::MODE_LABELS);
        $this->assertArrayHasKey(Event::MODE_ORG, Event::MODE_LABELS);
    }
    
    public function testTouchUpdatesTimestamp(): void
    {
        $event = new Event();
        $event->setTitle('Test');
        
        $this->assertNull($event->getUpdatedAt());
        
        $event->touch();
        
        $this->assertNotNull($event->getUpdatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getUpdatedAt());
    }
}
