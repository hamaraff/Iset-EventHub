<?php

namespace App\Controller\Admin;

use App\Entity\Organization;
use App\Entity\User;
use App\Repository\OrganizationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepo): Response
    {
        $users = $userRepo->findAll();
        
        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
        ]);
    }
    
    #[Route('/{id}/roles', name: 'admin_user_roles', methods: ['GET', 'POST'])]
    public function manageRoles(
        User $user, 
        Request $request, 
        EntityManagerInterface $em
    ): Response {
        if ($request->isMethod('POST')) {
            $roles = $request->request->all('roles');
            
            // Always keep ROLE_PARTICIPANT
            if (!in_array('ROLE_PARTICIPANT', $roles)) {
                $roles[] = 'ROLE_PARTICIPANT';
            }
            
            // Prevent removing ROLE_ADMIN from yourself
            if ($user === $this->getUser() && !in_array('ROLE_ADMIN', $roles)) {
                $this->addFlash('error', 'You cannot remove your own admin role.');
                return $this->redirectToRoute('admin_user_roles', ['id' => $user->getId()]);
            }
            
            $user->setRoles($roles);
            $em->flush();
            
            $this->addFlash('success', 'Roles updated successfully.');
            return $this->redirectToRoute('admin_user_index');
        }
        
        return $this->render('admin/user/roles.html.twig', [
            'user' => $user,
            'availableRoles' => [
                'ROLE_PARTICIPANT' => 'Participant',
                'ROLE_ORGANIZER' => 'Organizer',
                'ROLE_STAFF' => 'Staff',
                'ROLE_ADMIN' => 'Administrator',
            ],
        ]);
    }
    
    #[Route('/create', name: 'admin_user_create', methods: ['GET', 'POST'])]
    public function create(
        Request $request, 
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        OrganizationRepository $organizationRepo
    ): Response {
        $organizations = $organizationRepo->findAll();

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $name = $request->request->get('name');
            $password = $request->request->get('password');
            $roles = $request->request->all('roles');
            $organizationIds = $request->request->all('organization_ids');

            // Check if user exists
            $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existing) {
                $this->addFlash('error', 'User with this email already exists.');
                return $this->redirectToRoute('admin_user_create');
            }

            $user = new User();
            $user->setEmail($email);
            $user->setName($name);
            $user->setRoles($roles);

            if ($password) {
                $hashedPassword = $passwordHasher->hashPassword($user, $password);
                $user->setPassword($hashedPassword);
            }

            if (in_array('ROLE_ORGANIZER', $roles, true)) {
                $organization = new Organization();
                $organization->setName(sprintf('%s Organization', $name));
                $organization->setDescription(sprintf('Automatically created organization for organizer %s.', $name));
                $organization->addMember($user);
                $em->persist($organization);
            }

            foreach ($organizationIds as $selectedId) {
                if (!$selectedId) {
                    continue;
                }

                $organization = $organizationRepo->find($selectedId);
                if ($organization) {
                    $user->addOrganization($organization);
                }
            }

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'User created successfully.');
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/create.html.twig', [
            'availableRoles' => [
                'ROLE_PARTICIPANT' => 'Participant',
                'ROLE_ORGANIZER' => 'Organizer',
                'ROLE_STAFF' => 'Staff',
                'ROLE_ADMIN' => 'Administrator',
            ],
            'organizations' => $organizations,
        ]);
    }
}
