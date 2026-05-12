<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard')]
    public function admin():Response{
        return $this->render('dashboard/index.html.twig'); 
        
    }

    #[Route('' , name:'organizer_dashboard')]
    public function organizer():Response{
        return $this->render('dashboard/index.html.twig');
        
    }

    #[Route('',name:'staff_dashboard')]
    public function staff():Response{
        return $this->render('dashboard/index.html.twig');
    }


}
