<?php

namespace App\Controller;

use App\Entity\User; 
use App\Entity\Post;
use App\Entity\Comment;
use App\Form\CommentType;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CommentController extends AbstractController
{
    private $entityManager;
    private $commentRepository;
    private $postRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        CommentRepository $commentRepository,
        PostRepository $postRepository
    ) {
        $this->entityManager = $entityManager;
        $this->commentRepository = $commentRepository;
        $this->postRepository = $postRepository;
    }

    // Afficher tous les commentaires d'un article
    #[Route('/post/{postId}/comments', name: 'post_comments')]
    public function showComments(int $postId): Response
    {
        // Récupérer l'article
        $post = $this->postRepository->find($postId);
        if (!$post) {
            throw $this->createNotFoundException('Article non trouvé.');
        }

        // Récupérer les commentaires (validés ou en attente selon l'utilisateur)
        if ($this->isGranted('ROLE_ADMIN')) {
            // L'administrateur voit tous les commentaires (validés et en attente)
            $comments = $this->commentRepository->findBy(['post' => $postId]);
        } else {
            // Les autres utilisateurs voient uniquement les commentaires validés
            $comments = $this->commentRepository->findBy(['post' => $postId, 'status' => 'validé']);
        }

        return $this->render('comment/index.html.twig', [
            'comments' => $comments,
            'post' => $post,
        ]);
    }

    // Ajouter un commentaire
    #[Route('/post/{postId}/comment', name: 'post_add_comment', methods: ['POST'])]
    public function addComment(int $postId, Request $request): Response
    {
        // Récupérer l'utilisateur actuel
        $user = $this->getUser();
    
        // Vérifiez si l'utilisateur est connecté et n'est pas un visiteur
        if (!$user || in_array('ROLE_VISITOR', $user->getRoles())) {
            $this->addFlash('error', 'Votre compte doit être validé pour commenter.');
            return $this->redirectToRoute('post_show', ['id' => $postId]);
        }
    
        // Récupérer l'article concerné
        $post = $this->postRepository->find($postId);
        if (!$post) {
            throw $this->createNotFoundException('Article non trouvé.');
        }
    
        // Créer un nouveau commentaire
        $comment = new Comment();
        $comment->setPost($post);
        $comment->setUser($user);
        $comment->setCreatedAt(new \DateTime());
        $comment->setStatus('en attente'); // Statut par défaut : "en attente"
        
        // Créer et traiter le formulaire
        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            // Enregistrer le commentaire
            $this->entityManager->persist($comment);
            $this->entityManager->flush();
    
            $this->addFlash('success', 'Votre commentaire est en attente de validation.');
            return $this->redirectToRoute('post_comments', ['postId' => $postId]);
        }
    
        return $this->render('comment/add.html.twig', [
            'form' => $form->createView(),
            'post' => $post,
        ]);
    }

    // Supprimer un commentaire
    #[Route('/post/comment/{commentId}/delete', name: 'post_delete_comment', methods: ['POST'])]
    public function deleteComment(int $commentId): Response
    {
        // Récupérer le commentaire via son ID
        $comment = $this->commentRepository->find($commentId);
        
        if (!$comment) {
            throw $this->createNotFoundException('Commentaire non trouvé.');
        }
    
        // Vérifier si l'utilisateur est un administrateur
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Supprimer le commentaire
        $this->entityManager->remove($comment);
        $this->entityManager->flush();
        
        // Ajouter un message flash pour notifier de la suppression
        $this->addFlash('success', 'Le commentaire a été supprimé avec succès.');
        
        // Rediriger vers la page de l'article
        return $this->redirectToRoute('post_show', ['id' => $comment->getPost()->getId()]);
    }

    // Valider un commentaire (administrateur)
    #[Route('/post/comment/{commentId}/validate', name: 'post_validate_comment', methods: ['POST'])]
    public function validateComment(int $commentId): Response
    {
        // Récupérer le commentaire via son ID
        $comment = $this->commentRepository->find($commentId);

        if (!$comment) {
            throw $this->createNotFoundException('Commentaire non trouvé.');
        }

        // Vérifier si l'utilisateur est un administrateur
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Mettre à jour le statut du commentaire en "validé"
        $comment->setStatus('validé');
        $this->entityManager->flush();
        
        // Ajouter un message flash pour notifier de la validation
        $this->addFlash('success', 'Le commentaire a été validé avec succès.');
        
        // Rediriger vers la page des commentaires
        return $this->redirectToRoute('post_comments', ['postId' => $comment->getPost()->getId()]);
    }
    
}