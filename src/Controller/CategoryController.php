<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;

class CategoryController extends AbstractController
{
    // Liste des catégories
    #[Route('/categories', name: 'category_list')]
    public function list(CategoryRepository $categoryRepository): Response
    {
        $categories = $categoryRepository->findAll();

        return $this->render('category/list.html.twig', [
            'categories' => $categories,
        ]);
    }

    // Affichage d'une catégorie et ses articles
    #[Route('/category/{id}', name: 'category_show', requirements: ['id' => '\d+'])]
    public function show(int $id, CategoryRepository $categoryRepository): Response
    {
        $category = $categoryRepository->find($id);

        if (!$category) {
            throw $this->createNotFoundException('La catégorie demandée n\'existe pas.');
        }

        $posts = $category->getPosts();

        return $this->render('category/show.html.twig', [
            'category' => $category,
            'posts' => $posts,
        ]);
    }

    // Création d'une nouvelle catégorie
    #[Route('/category/new', name: 'category_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        // Restriction d'accès
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($category);
            $em->flush();

            $this->addFlash('success', 'Catégorie ajoutée avec succès.');

            return $this->redirectToRoute('category_list');
        }

        return $this->render('category/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // Suppression d'une catégorie
    #[Route('/category/{id}/delete', name: 'category_delete', methods: ['POST'])]
    public function delete(
        Category $category, 
        Request $request, 
        EntityManagerInterface $entityManager, 
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        // Restriction d'accès
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Vérifier le token CSRF
        $token = $request->request->get('_token');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('delete' . $category->getId(), $token))) {
            throw $this->createAccessDeniedException('Action non autorisée.');
        }

        // Vérifier si la catégorie a des articles associés
        if (!$category->getPosts()->isEmpty()) {
            $this->addFlash('warning', 'Vous ne pouvez pas supprimer une catégorie contenant des articles.');
            return $this->redirectToRoute('category_list');
        }

        // Supprimer la catégorie
        $entityManager->remove($category);
        $entityManager->flush();

        $this->addFlash('success', 'Catégorie supprimée avec succès.');

        return $this->redirectToRoute('category_list');
    }
}