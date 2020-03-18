<?php


namespace App\Controller;


use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;



class IndexUserController extends AbstractController
{

    public function __invoke(UserRepository $userRepository)
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

}