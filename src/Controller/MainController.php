<?php

namespace App\Controller;

use App\Repository\PostRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MainController extends AbstractController
{
    #[Route('/main', name: 'main')]
    public function index(PostRepository $postRepository, CategoryRepository $categoryRepository): Response
    {
        // Récupérer les articles récents (par exemple, les 5 derniers)
        $posts = $postRepository->findBy([], ['publishedAt' => 'DESC'], 5);

        // Récupérer toutes les catégories
        $categories = $categoryRepository->findAll();

        // Passer les données au template
        return $this->render('main/index.html.twig', [
            'controller_name' => 'MainController',
            'posts' => $posts,
            'categories' => $categories,
        ]);
    }
}