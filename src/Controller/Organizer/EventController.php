<?php

namespace App\Controller\Organizer;

use App\Entity\Event;
use App\Form\EventType;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/organizer/events')]
final class EventController extends AbstractController
{
    //List Events
    #[Route('/my_events',name:'organizer_event_index',methods:['GET'])]
    public function index(EntityManagerInterface $em, EventRepository $eventRepository):Response
    {
        $user = $this->getUser();

        $organizations = $user->getOrganizations();

        $events = $eventRepository->createQueryBuilder('e')
            ->where('e.organization IN (:orgs)')
            ->setParameter('orgs', $organizations)
            ->getQuery()
            ->getResult();
        return $this->render('organizer/event/index.html.twig', [
            'events' => $events,
        ]);
    }

    //Create Event
    #[Route('/create',name:'organizer_event_new')]
    public function new(Request $request, EntityManagerInterface $em, EventRepository $eventRepository, SluggerInterface $slugger):Response
    {
        $user = $this->getUser();
        $event = new Event();
        
        $form = $this->createForm(EventType::class, $event, [
            'user_organizations' => $user->getOrganizations(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($eventRepository->hasDateConflict($event->getStartDate(),$event->getEndDate(),null)) {
                $this->addFlash('error', 'Date conflict with another event.');
                return $this->redirectToRoute('organizer_event_index');
            }

            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $targetDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/events';
                if (!is_dir($targetDirectory)) {
                    mkdir($targetDirectory, 0755, true);
                }

                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $extension = $imageFile->guessExtension() ?: $imageFile->getClientOriginalExtension();
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

                try {
                    $imageFile->move($targetDirectory, $newFilename);
                    $event->setImagePath($newFilename);
                } catch (FileException $exception) {
                    $this->addFlash('error', 'Unable to upload event image.');
                    return $this->redirectToRoute('organizer_event_index');
                }
            }

            $em->persist($event);
            $em->flush();

            $this->addFlash('success', 'Event created.');

            return $this->redirectToRoute('calendar_index', [
                'year' => (int) $event->getStartDate()->format('Y'),
                'month' => (int) $event->getStartDate()->format('n'),
            ]);
        }
        
        return $this->render('organizer/event/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    //Edit Event
    #[Route('/{id}/edit',name:'organizer_event_edit')]
    #[IsGranted('EVENT_EDIT', subject: 'event')]
    public function edit(Event $event , Request $request,EntityManagerInterface $em , EventRepository $eventRepository, SluggerInterface $slugger):Response
    {
        $user = $this->getUser();
        $form = $this->createForm(EventType::class,$event,[
            'user_organizations' => $user->getOrganizations(),
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted()&&$form->isValid()){
            if ($eventRepository->hasDateConflict($event->getStartDate(),$event->getEndDate(),$event->getId())) {
                $this->addFlash('error', 'Date conflict with another event.');
                return $this->redirectToRoute('organizer_event_index');
            }

            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $targetDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/events';
                if (!is_dir($targetDirectory)) {
                    mkdir($targetDirectory, 0755, true);
                }

                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $extension = $imageFile->guessExtension() ?: $imageFile->getClientOriginalExtension();
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

                try {
                    $imageFile->move($targetDirectory, $newFilename);
                    $event->setImagePath($newFilename);
                } catch (FileException $exception) {
                    $this->addFlash('error', 'Unable to upload event image.');
                    return $this->redirectToRoute('organizer_event_index');
                }
            }

            // approved → reset logic
            if ($event->getStatus() === Event::STATUS_APPROVED) {
                $event->markAsPendingAfterApprovedEdit();
                $this->addFlash('info', 'Event was approved - resetting to pending for re-validation.');
            }
            $event->touch();
            $em->flush();
            $this->addFlash('success','Event UPDATED');
            return $this->redirectToRoute('organizer_event_index');
        }
        return $this->render('organizer/event/edit.html.twig',[
            'form' => $form->createView(),
            'event' => $event,
        ]);
    }

    //Submit for validation

    #[Route('/{id}/submit',name:'organizer_event_submit',methods:['POST'])]
    #[IsGranted('EVENT_SUBMIT', subject: 'event')]
    public function submit(Event $event , EntityManagerInterface $em):Response
    {
        if($event->getStatus()==Event::STATUS_DRAFT){
            $event->setStatus(Event::STATUS_PENDING);
            $event->touch();
            $em->flush();
            $this->addFlash('success','Event t3mall ll validation (SUBMITTED)');
        }
        return $this->redirectToRoute('organizer_event_index');
    }

    //Delete DRAFT Only

    #[Route('/{id}/delete',name:'organizer_event_delete',methods:['POST'])]
    #[IsGranted('EVENT_DELETE', subject: 'event')]
    public function delete(Event $event , EntityManagerInterface $em):Response
    {
        if($event->getStatus()!==Event::STATUS_DRAFT){
            $this->addFlash('error','M TNAJJEM TAFSEKH ken DRAFT EVENT');
            return $this->redirectToRoute('organizer_event_index');
        }
        $em->remove($event);
        $em->flush();
        $this->addFlash('success','Event deleted');
        return $this->redirectToRoute('organizer_event_index');
    }
}
