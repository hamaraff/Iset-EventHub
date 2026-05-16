<?php

namespace App\Controller\Organizer;

use App\Entity\Organization;
use App\Form\OrganizationType;
use App\Repository\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/organizer/organizations')]
final class OrganizationController extends AbstractController
{
    #[Route('', name: 'organizer_organization_index', methods: ['GET'])]
    public function index(OrganizationRepository $organizationRepo): Response
    {
        $user = $this->getUser();
        $organizations = $user->getOrganizations();
        
        return $this->render('organizer/organization/index.html.twig', [
            'organizations' => $organizations,
        ]);
    }
    
    #[Route('/create', name: 'organizer_organization_create', methods: ['GET', 'POST'])]
public function create(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $organization = new Organization();
        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $logoFile */
            $logoFile = $form->get('logo')->getData();
            if ($logoFile) {
                $targetDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/organizations';
                if (!is_dir($targetDirectory)) {
                    mkdir($targetDirectory, 0755, true);
                }

                $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $extension = $logoFile->guessExtension() ?: $logoFile->getClientOriginalExtension();
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

                try {
                    $logoFile->move($targetDirectory, $newFilename);
                    $organization->setLogo($newFilename);
                } catch (FileException $exception) {
                    $this->addFlash('error', 'Unable to upload organization logo.');
                    return $this->redirectToRoute('organizer_organization_create');
                }
            }

            $user = $this->getUser();
            $organization->addMember($user);
            
            $em->persist($organization);
            $em->flush();

            $this->addFlash('success', 'Organization created successfully.');
            return $this->redirectToRoute('organizer_organization_index');
        }
        
        return $this->render('organizer/organization/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    
    #[Route('/{id}/edit', name: 'organizer_organization_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ORGANIZATION_EDIT', subject: 'organization')]
    public function edit(Organization $organization, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $logoFile */
            $logoFile = $form->get('logo')->getData();
            if ($logoFile) {
                $targetDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/organizations';
                if (!is_dir($targetDirectory)) {
                    mkdir($targetDirectory, 0755, true);
                }

                $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $extension = $logoFile->guessExtension() ?: $logoFile->getClientOriginalExtension();
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

                try {
                    $logoFile->move($targetDirectory, $newFilename);
                    $organization->setLogo($newFilename);
                } catch (FileException $exception) {
                    $this->addFlash('error', 'Unable to upload organization logo.');
                    return $this->redirectToRoute('organizer_organization_edit', ['id' => $organization->getId()]);
                }
            }

            $em->flush();

            $this->addFlash('success', 'Organization updated successfully.');
            return $this->redirectToRoute('organizer_organization_index');
        }
        
        return $this->render('organizer/organization/edit.html.twig', [
            'form' => $form->createView(),
            'organization' => $organization,
        ]);
    }
    
    #[Route('/{id}/members', name: 'organizer_organization_members', methods: ['GET', 'POST'])]
    #[IsGranted('ORGANIZATION_MANAGE_MEMBERS', subject: 'organization')]
    public function manageMembers(Organization $organization, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');
            $userId = $request->request->get('user_id');
            
            $userRepository = $em->getRepository(\App\Entity\User::class);
            $user = $userRepository->find($userId);
            
            if (!$user) {
                $this->addFlash('error', 'User not found.');
                return $this->redirectToRoute('organizer_organization_members', ['id' => $organization->getId()]);
            }
            
            if ($action === 'add') {
                if (!$organization->getMembers()->contains($user)) {
                    $organization->addMember($user);
                    $em->flush();
                    $this->addFlash('success', 'User added to organization.');
                } else {
                    $this->addFlash('error', 'User is already a member of this organization.');
                }
            } elseif ($action === 'remove') {
                // Prevent removing yourself if you're the only member
                if ($user === $this->getUser() && $organization->getMembers()->count() === 1) {
                    $this->addFlash('error', 'Cannot remove yourself as the only member of the organization.');
                } elseif ($organization->getMembers()->contains($user)) {
                    $organization->removeMember($user);
                    $em->flush();
                    $this->addFlash('success', 'User removed from organization.');
                } else {
                    $this->addFlash('error', 'User is not a member of this organization.');
                }
            }
            
            return $this->redirectToRoute('organizer_organization_members', ['id' => $organization->getId()]);
        }
        
        $userRepository = $em->getRepository(\App\Entity\User::class);
        $nonMembers = $userRepository->createQueryBuilder('u')
            ->where(':organization NOT MEMBER OF u.organizations')
            ->setParameter('organization', $organization)
            ->getQuery()
            ->getResult();
        
        return $this->render('organizer/organization/members.html.twig', [
            'organization' => $organization,
            'members' => $organization->getMembers(),
            'nonMembers' => $nonMembers,
        ]);
    }
    
    #[Route('/{id}/view', name: 'organizer_organization_view', methods: ['GET'])]
    #[IsGranted('ORGANIZATION_VIEW', subject: 'organization')]
    public function view(Organization $organization): Response
    {
        return $this->render('organizer/organization/view.html.twig', [
            'organization' => $organization,
        ]);
    }
}
