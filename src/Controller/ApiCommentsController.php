<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class ApiCommentsController extends AbstractController
{
    /**
     * @Route("/api/comments", name="api_comments")
     */
    public function index()
    {
        return $this->render('api_comments/index.html.twig', [
            'controller_name' => 'ApiCommentsController',
        ]);
    }
}
