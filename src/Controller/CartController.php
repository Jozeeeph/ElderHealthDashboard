<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Repository\EquipementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class CartController extends AbstractController
{
    private EntityManagerInterface $em;
    private EquipementRepository $equipRepo;
    private Security $security;

    public function __construct(EntityManagerInterface $em, EquipementRepository $equipRepo, Security $security)
    {
        $this->em = $em;
        $this->equipRepo = $equipRepo;
        $this->security = $security;
    }

    #[Route('/cart', name: 'cart_view')]
    public function view(SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $equipements = $cart ? $this->equipRepo->findBy(['id' => $cart]) : [];

        $total = '0.00';
        foreach ($equipements as $e) {
            $total = bcadd($total, $e->getPrix() ?? '0.00', 2);
        }

        return $this->render('FrontOffice/equipement/cart.html.twig', [
            'equipements' => $equipements,
            'total' => $total,
        ]);
    }

    #[Route('/cart/add/{id}', name: 'cart_add')]
    public function addToCart(int $id, SessionInterface $session): Response
    {
        $equip = $this->equipRepo->find($id);
        if (!$equip) {
            throw $this->createNotFoundException('Equipement introuvable');
        }

        $cart = $session->get('cart', []);
        if (!in_array($id, $cart, true)) {
            $cart[] = $id;
            $session->set('cart', $cart);
        }

        return $this->redirectToRoute('cart_view');
    }

    #[Route('/cart/remove/{id}', name: 'cart_remove')]
    public function removeFromCart(int $id, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $cart = array_values(array_filter($cart, fn($item) => $item !== $id));
        $session->set('cart', $cart);

        return $this->redirectToRoute('cart_view');
    }

    #[Route('/cart/checkout', name: 'cart_checkout', methods: ['POST', 'GET'])]
    public function checkout(SessionInterface $session, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $cart = $session->get('cart', []);
        if (empty($cart)) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('cart_view');
        }

        $user = $this->getUser();

        $commande = new Commande();
        $commande->setUtilisateur($user);
        $commande->setDateCommande(new \DateTime());
        $commande->setStatutCommande(Commande::STATUT_EN_ATTENTE);

        foreach ($cart as $id) {
            $equip = $this->equipRepo->find($id);
            if ($equip) {
                $commande->addEquipement($equip);
            }
        }

        $this->em->persist($commande);
        $this->em->flush();

        $session->remove('cart');

        $this->addFlash('success', 'Commande créée avec succès.');

        return $this->redirectToRoute('cart_view');
    }
}
