<?php

namespace App\Controller\Admin;

use App\Entity\Organization;
use App\Form\OrganizationType;
use App\Repository\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/organizations')]
#[IsGranted('ROLE_ADMIN')]
final class OrganizationController extends AbstractController
{
    #[Route('', name: 'admin_organization_index', methods: ['GET'])]
    public function index(OrganizationRepository $organizationRepo): Response
    {
        $organizations = $organizationRepo->findAll();
        
        return $this->render('admin/organization/index.html.twig', [
            'organizations' => $organizations,
        ]);
    }
    
    #[Route('/create', name: 'admin_organization_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $organization = new Organization();
        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($organization);
            $em->flush();

            $this->addFlash('success', 'Organization created successfully.');
            return $this->redirectToRoute('admin_organization_index');
        }
        
        return $this->render('admin/organization/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    
    #[Route('/{id}/edit', name: 'admin_organization_edit', methods: ['GET', 'POST'])]
    public function edit(Organization $organization, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Organization updated successfully.');
            return $this->redirectToRoute('admin_organization_index');
        }
        
        return $this->render('admin/organization/edit.html.twig', [
            'form' => $form->createView(),
            'organization' => $organization,
        ]);
    }
    
    #[Route('/{id}/delete', name: 'admin_organization_delete', methods: ['POST'])]
    public function delete(Organization $organization, EntityManagerInterface $em): Response
    {
        // Check if organization has events
        if (!$organization->getEvents()->isEmpty()) {
            $this->addFlash('error', 'Cannot delete organization with events. Please delete or reassign events first.');
            return $this->redirectToRoute('admin_organization_index');
        }
        
        $em->remove($organization);
        $em->flush();
        
        $this->addFlash('success', 'Organization deleted successfully.');
        return $this->redirectToRoute('admin_organization_index');
    }
    
    #[Route('/{id}/members', name: 'admin_organization_members', methods: ['GET', 'POST'])]
    public function manageMembers(Organization $organization, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');
            $userId = $request->request->get('user_id');
            
            $userRepository = $em->getRepository(\App\Entity\User::class);
            $user = $userRepository->find($userId);
            
            if (!$user) {
                $this->addFlash('error', 'User not found.');
                return $this->redirectToRoute('admin_organization_members', ['id' => $organization->getId()]);
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
                if ($organization->getMembers()->contains($user)) {
                    $organization->removeMember($user);
                    $em->flush();
                    $this->addFlash('success', 'User removed from organization.');
                } else {
                    $this->addFlash('error', 'User is not a member of this organization.');
                }
            }
            
            return $this->redirectToRoute('admin_organization_members', ['id' => $organization->getId()]);
        }
        
        $userRepository = $em->getRepository(\App\Entity\User::class);
        $nonMembers = $userRepository->createQueryBuilder('u')
            ->where(':organization NOT MEMBER OF u.organizations')
            ->setParameter('organization', $organization)
            ->getQuery()
            ->getResult();
        
        return $this->render('admin/organization/members.html.twig', [
            'organization' => $organization,
            'members' => $organization->getMembers(),
            'nonMembers' => $nonMembers,
        ]);
    }
}
