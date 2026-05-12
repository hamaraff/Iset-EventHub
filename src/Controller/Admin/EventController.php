<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Event;

#[Route('/admin/events')]
final class EventController extends AbstractController
{
    //List Pending Events 

    #[Route('/pending',name:'admin_event_pending',methods:['GET'])]
    public function pending(EntityManagerInterface $em):Response
    {
        $events = $em->getRepository(Event::class)->findBy(['status'=>Event::STATUS_PENDING]);
        return $this->render('admin/event/pending.html.twig',['events'=>$events]);
    }

    //Approve Event
    #[Route('/{id}/approve',name:'admin_event_approve',methods: ['POST'])]
    public function approve(Event $event , EntityManagerInterface $em):Response
    {
        if($event->getStatus()!==Event::STATUS_PENDING){
            $this->addFlash('error','Only Pending Events Can Be Approved !!');
            return $this->redirectToRoute('admin_event_pending');
        }
        $event->setStatus(Event::STATUS_APPROVED);
        $event->touch();
        $em->flush();
        $this->addFlash('success','Event approved');
        return $this->redirectToRoute('admin_event_pending');
    }

    //Refuse Event
    #[Route('/{id}/refuse',name:'admin_event_refuse',methods:['POST'])]
    public function refuse(Event $event , EntityManagerInterface $em):Response
    {
        if($event->getStatus()!==Event::STATUS_PENDING){
            $this->addFlash('error','Only Pending events can be refused !!!');
            return $this->redirectToRoute('admin_event_pending');
        }
        $event->setStatus(Event::STATUS_REFUSED);
        $event->touch();
        $em->flush();
        $this->addFlash('success','Event refused');
        return $this->redirectToRoute('admin_event_pending');
    }
}
