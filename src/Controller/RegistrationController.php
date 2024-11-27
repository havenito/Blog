<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $passwordHasher, 
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        // Vérifie si l'utilisateur est déjà connecté
        if ($this->getUser()) {
            return $this->redirectToRoute('main');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion du fichier uploadé
            $profilePictureFile = $form->get('profilePicture')->getData();
            if ($profilePictureFile) {
                $originalFilename = pathinfo($profilePictureFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$profilePictureFile->guessExtension();

                try {
                    // Déplace le fichier dans le répertoire 'uploads/posts'
                    $profilePictureFile->move(
                        $this->getParameter('uploads_directory'), // Répertoire où l'image sera stockée, qui est 'uploads/posts'
                        $newFilename
                    );

                    // Met à jour la propriété `profilePicture` de l'utilisateur
                    $user->setProfilePicture($newFilename);
                } catch (FileException $e) {
                    // Si une erreur se produit lors du déplacement du fichier, on informe l'utilisateur
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'upload de la photo de profil. Veuillez réessayer.');
                    return $this->render('registration/register.html.twig', [
                        'registrationForm' => $form->createView(),
                    ]);
                }
            }

            // Hash le mot de passe
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );
            $user->setRoles(['ROLE_VISITOR']); // Assigner un rôle par défaut

            // Sauvegarder l'utilisateur dans la base de données
            $entityManager->persist($user);
            $entityManager->flush();

            // Redirige après inscription
            $this->addFlash('success', 'Inscription réussie, vous pouvez vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}