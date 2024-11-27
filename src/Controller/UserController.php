<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class UserController extends AbstractController
{
    #[Route('/users', name: 'user_list')]
    public function list(UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $users = $userRepository->findAll(); 

        return $this->render('user/list.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/users/{id}', name: 'user_show', requirements: ['id' => '\d+'])]
    public function show(User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/admin/users/{id}/accept', name: 'user_accept', requirements: ['id' => '\d+'])]
    public function acceptUser(User $user, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user->setIsAccepted(true);
        $user->setRoles(['ROLE_USER']); 
        $entityManager->flush(); 

        $this->addFlash('success', "L'utilisateur a été accepté avec succès.");
        return $this->redirectToRoute('user_list');
    }

    #[Route('/admin/users/{id}/reject', name: 'user_reject', requirements: ['id' => '\d+'])]
    public function refuseUser(User $user, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $user->setIsAccepted(false);
        $user->setRoles(['ROLE_VISITOR']); 
        
        $entityManager->flush(); 
    
        $this->addFlash('info', "L'utilisateur a été refusé et rétrogradé au rôle de visiteur.");
        return $this->redirectToRoute('user_list');
    }

    #[Route('/admin/users/{id}/deactivate', name: 'user_deactivate', requirements: ['id' => '\d+'])]
    public function deactivateUser(User $user, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $user->setIsActive(false);
        $entityManager->flush(); 

        $this->addFlash('warning', "L'utilisateur a été désactivé.");
        return $this->redirectToRoute('user_list');
    }

    #[Route('/admin/users/{id}/activate', name: 'user_activate', requirements: ['id' => '\d+'])]
    public function activateUser(User $user, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $user->setIsActive(true);
        $entityManager->flush(); 

        $this->addFlash('success', "L'utilisateur a été réactivé.");
        return $this->redirectToRoute('user_list');
    }
}