<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Post;
use App\Entity\Comment;
use App\Form\PostType;
use App\Form\CommentType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Entity\Reply; 
use App\Form\ReplyFormType;

class PostController extends AbstractController
{
    #[Route('/post/create', name: 'post_create')]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Vérifier si l'utilisateur a le rôle ROLE_ADMIN
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à créer des articles.');
        }
    
        $post = new Post();
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de l'image téléchargée
            $uploadedFile = $form->get('picture')->getData();
            if ($uploadedFile) {
                // Récupérer le répertoire des uploads depuis les paramètres
                $uploadDir = $this->getParameter('uploads_directory');
                $newFilename = uniqid() . '.' . $uploadedFile->guessExtension();
    
                try {
                    $uploadedFile->move($uploadDir, $newFilename);
                    $post->setPicture($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'upload de l\'image.');
                    return $this->redirectToRoute('post_create');
                }
            }
    
            // Associer l'utilisateur actuellement connecté
            $post->setUser($this->getUser());
    
            // Vérifier ou définir la date de publication
            if (!$post->getPublishedAt()) {
                $post->setPublishedAt(new \DateTime()); // Définit la date actuelle si aucune date n'est fournie
            }
    
            // Sauvegarder dans la base de données
            $entityManager->persist($post);
            $entityManager->flush();
    
            $this->addFlash('success', 'L\'article a été créé avec succès.');
    
            // Rediriger vers l'affichage de l'article
            return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
        }
    
        return $this->render('post/form.html.twig', [
            'form' => $form->createView(),
            'post' => $post,
        ]);
    }

    #[Route('/posts', name: 'post_list')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $posts = $entityManager->getRepository(Post::class)->findAll();

        return $this->render('post/list.html.twig', [
            'posts' => $posts,
        ]);
    }

    #[Route('/post/{id}', name: 'post_show')]
    public function show(Request $request, EntityManagerInterface $entityManager, ?Post $post): Response
    {
        if (!$post) {
            throw $this->createNotFoundException('Article introuvable.');
        }
    
        // Formulaire pour ajouter un commentaire
        $comment = new Comment();
        $commentForm = $this->createForm(CommentType::class, $comment);
        $commentForm->handleRequest($request);
    
        // Formulaire pour ajouter une réponse
        $replyForms = [];
    
        // Si le formulaire de commentaire est soumis et valide
        if ($commentForm->isSubmitted() && $commentForm->isValid()) {
            $comment->setPost($post);
            $comment->setUser($this->getUser());
            $comment->setCreatedAt(new \DateTime());
    
            $entityManager->persist($comment);
            $entityManager->flush();
    
            $this->addFlash('success', 'Votre commentaire a été ajouté.');
            return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
        }
    
        // Si le formulaire de réponse est soumis et valide
        if ($request->isMethod('POST') && isset($request->request->get('reply')['content'])) {
            $replyContent = $request->request->get('reply')['content'];
            $commentId = $request->request->get('reply')['commentId'];
        
            $comment = $entityManager->getRepository(Comment::class)->find($commentId);
        
            if ($comment && $replyContent) {
                $reply = new Comment();
                $reply->setContent($replyContent);
                $reply->setUser($this->getUser());
                $reply->setCreatedAt(new \DateTime());
                $reply->setPost($post);
                $reply->setParentComment($comment);  // Définir la réponse comme un enfant du commentaire
        
                $entityManager->persist($reply);
                $entityManager->flush();
        
                // Ajout du flash message
                $this->addFlash('success', 'Votre réponse a été ajoutée.');
            }
        
            // Rediriger pour afficher la réponse directement sous le commentaire
            return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
        }
    
        // Générer les formulaires de réponse pour chaque commentaire
        foreach ($post->getComments() as $comment) {
            $replyForm = $this->createForm(CommentType::class, new Comment());
            $replyForms[$comment->getId()] = $replyForm->createView(); // Créez une vue du formulaire
        }
    
        return $this->render('post/show.html.twig', [
            'post' => $post,
            'commentForm' => $commentForm->createView(),
            'replyForms' => $replyForms,
        ]);
    }

    // Route pour modifier un article (admin uniquement)
#[Route('/post/edit/{id}', name: 'post_edit')]
public function edit(Request $request, EntityManagerInterface $entityManager, Post $post): Response
{
    // Vérifier si l'utilisateur est administrateur ou propriétaire du post
    if (!$this->isGranted('ROLE_ADMIN') && $this->getUser() !== $post->getUser()) {
        throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cet article.');
    }

    // Créer le formulaire
    $form = $this->createForm(PostType::class, $post);
    $form->handleRequest($request);

    // Lorsque le formulaire est soumis et valide
    if ($form->isSubmitted() && $form->isValid()) {
        $uploadedFile = $form->get('picture')->getData();

        if ($uploadedFile instanceof UploadedFile) {
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            $extension = $uploadedFile->guessExtension();
            if (!in_array($extension, $allowedExtensions)) {
                $this->addFlash('error', 'Le type de fichier n\'est pas autorisé.');
                return $this->redirectToRoute('post_edit', ['id' => $post->getId()]);
            }

            $uploadDir = $this->getParameter('uploads_directory');
            $oldFilename = $post->getPicture();
            if ($oldFilename && file_exists($uploadDir . '/' . $oldFilename)) {
                unlink($uploadDir . '/' . $oldFilename);
            }

            $newFilename = uniqid() . '.' . $extension;
            try {
                $uploadedFile->move($uploadDir, $newFilename);
                $post->setPicture($newFilename);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors du téléchargement de l\'image.');
                return $this->redirectToRoute('post_edit', ['id' => $post->getId()]);
            }
        }

        // Mettre à jour l'article dans la base de données
        $entityManager->flush();

        // Rediriger vers la page de l'article mis à jour
        $this->addFlash('success', 'L\'article a été mis à jour avec succès.');
        return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
    }

    return $this->render('post/edit.html.twig', [
        'form' => $form->createView(),
        'post' => $post,
    ]);
}

    // Route pour supprimer un article (admin uniquement)
    #[Route('/admin/post/{id}/delete', name: 'post_delete')]
    public function delete(Post $post, EntityManagerInterface $entityManager): Response
    {
        // Vérifie si l'utilisateur est un administrateur
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $entityManager->remove($post);
        $entityManager->flush();

        $this->addFlash('success', 'Article supprimé avec succès.');
        return $this->redirectToRoute('post_list');
    }
}