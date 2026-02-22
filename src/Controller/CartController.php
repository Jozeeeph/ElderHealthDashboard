<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Repository\EquipementRepository;
use App\Service\StripeCheckoutService;
use App\Service\TwilioSmsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;

class CartController extends AbstractController
{
    private EntityManagerInterface $em;
    private EquipementRepository $equipRepo;
    private Security $security;
    private TwilioSmsService $twilioSmsService;
    private StripeCheckoutService $stripeCheckoutService;

    public function __construct(
        EntityManagerInterface $em,
        EquipementRepository $equipRepo,
        Security $security,
        TwilioSmsService $twilioSmsService,
        StripeCheckoutService $stripeCheckoutService
    ) {
        $this->em = $em;
        $this->equipRepo = $equipRepo;
        $this->security = $security;
        $this->twilioSmsService = $twilioSmsService;
        $this->stripeCheckoutService = $stripeCheckoutService;
    }

    #[Route('/cart', name: 'cart_view')]
    public function view(SessionInterface $session): Response
    {
        $cart = $this->normalizeCart($session->get('cart', []));
        $session->set('cart', $cart);
        $ids = array_map('intval', array_keys($cart));
        $equipements = $ids ? $this->equipRepo->findBy(['id' => $ids]) : [];

        $total = '0.00';
        $lines = [];
        $itemsCount = 0;
        foreach ($equipements as $e) {
            $id = $e->getId();
            if ($id === null) {
                continue;
            }

            $qty = max(1, (int) ($cart[$id] ?? 1));
            $itemsCount += $qty;
            $lineTotal = bcmul((string) ($e->getPrix() ?? '0.00'), (string) $qty, 2);
            $total = bcadd($total, $lineTotal, 2);
            $lines[] = [
                'equipement' => $e,
                'quantity' => $qty,
                'line_total' => $lineTotal,
            ];
        }

        return $this->render('FrontOffice/equipement/cart.html.twig', [
            'lines' => $lines,
            'total' => $total,
            'items_count' => $itemsCount,
        ]);
    }

    #[Route('/cart/add/{id}', name: 'cart_add', methods: ['GET', 'POST'])]
    public function addToCart(int $id, SessionInterface $session, Request $request): Response
    {
        $equip = $this->equipRepo->find($id);
        if (!$equip) {
            throw $this->createNotFoundException('Equipement introuvable');
        }

        $requestedQty = (int) $request->request->get('quantity', $request->query->getInt('quantity', 1));
        $requestedQty = max(1, $requestedQty);
        $availableQty = max(0, (int) ($equip->getQuantiteDisponible() ?? 0));

        if ($availableQty <= 0) {
            $this->addFlash('error', 'Cet equipement est en rupture de stock.');
            return $this->redirectToRoute('cart_view');
        }

        if ($requestedQty > $availableQty) {
            $this->addFlash(
                'warning',
                sprintf(
                    'Quantite demandee trop grande pour %s. Choisissez une quantite entre 1 et %d.',
                    $equip->getNom() ?? ('#' . $id),
                    $availableQty
                )
            );
            return $this->redirectToRoute('cart_view');
        }

        $cart = $this->normalizeCart($session->get('cart', []));
        $currentQty = (int) ($cart[$id] ?? 0);
        $newQty = $currentQty + $requestedQty;

        if ($newQty > $availableQty) {
            $this->addFlash(
                'warning',
                sprintf(
                    'Stock insuffisant pour %s. Maximum disponible: %d.',
                    $equip->getNom() ?? ('#' . $id),
                    $availableQty
                )
            );
            return $this->redirectToRoute('cart_view');
        }

        $cart[$id] = max(1, $newQty);
        $session->set('cart', $cart);

        return $this->redirectToRoute('cart_view');
    }

    #[Route('/cart/remove/{id}', name: 'cart_remove')]
    public function removeFromCart(int $id, SessionInterface $session): Response
    {
        $cart = $this->normalizeCart($session->get('cart', []));
        unset($cart[$id]);
        $session->set('cart', $cart);

        return $this->redirectToRoute('cart_view');
    }

    #[Route('/cart/update/{id}', name: 'cart_update', methods: ['POST'])]
    public function updateQuantity(int $id, SessionInterface $session, Request $request): Response
    {
        $equip = $this->equipRepo->find($id);
        if (!$equip) {
            throw $this->createNotFoundException('Equipement introuvable');
        }

        $quantity = max(1, (int) $request->request->get('quantity', 1));
        $availableQty = max(0, (int) ($equip->getQuantiteDisponible() ?? 0));
        if ($availableQty <= 0) {
            $this->addFlash('error', 'Cet equipement est en rupture de stock.');
            return $this->redirectToRoute('cart_view');
        }

        if ($quantity > $availableQty) {
            $this->addFlash(
                'warning',
                sprintf(
                    'Quantite demandee trop grande pour %s. Choisissez une quantite entre 1 et %d.',
                    $equip->getNom() ?? ('#' . $id),
                    $availableQty
                )
            );
            return $this->redirectToRoute('cart_view');
        }

        $cart = $this->normalizeCart($session->get('cart', []));
        $cart[$id] = $quantity;
        $session->set('cart', $cart);

        return $this->redirectToRoute('cart_view');
    }

    #[Route('/cart/checkout', name: 'cart_checkout', methods: ['POST', 'GET'])]
    public function checkout(SessionInterface $session): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $cart = $this->normalizeCart($session->get('cart', []));
        if (empty($cart)) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('cart_view');
        }

        $ids = array_map('intval', array_keys($cart));
        $equipements = $this->equipRepo->findBy(['id' => $ids]);
        $equipementsById = [];
        foreach ($equipements as $equipement) {
            $eid = $equipement->getId();
            if ($eid !== null) {
                $equipementsById[$eid] = $equipement;
            }
        }

        // Validate stock before creating Stripe session.
        foreach ($cart as $id => $qty) {
            $id = (int) $id;
            $qty = max(1, (int) $qty);
            $equip = $equipementsById[$id] ?? null;

            if (!$equip) {
                $this->addFlash('error', sprintf("L'equipement #%d n'existe plus.", $id));
                return $this->redirectToRoute('cart_view');
            }

            $available = max(0, (int) ($equip->getQuantiteDisponible() ?? 0));
            if ($available < $qty) {
                $this->addFlash(
                    'error',
                    sprintf(
                        'Stock insuffisant pour %s: demande %d, disponible %d.',
                        $equip->getNom() ?? ('#' . $id),
                        $qty,
                        $available
                    )
                );
                return $this->redirectToRoute('cart_view');
            }
        }

        $items = [];
        foreach ($cart as $id => $qty) {
            $id = (int) $id;
            $qty = max(1, (int) $qty);
            $equip = $equipementsById[$id] ?? null;
            if (!$equip) {
                continue;
            }

            $items[] = [
                'name' => (string) ($equip->getNom() ?: ('Equipement #' . $id)),
                'unit_amount' => (int) round(((float) ($equip->getPrix() ?? '0')) * 100),
                'quantity' => $qty,
            ];
        }

        try {
            $successUrl = $this->generateUrl('cart_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl = $this->generateUrl('cart_payment_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL);

            $checkoutUrl = $this->stripeCheckoutService->createCartCheckoutUrl(
                $items,
                $this->getUser(),
                $successUrl,
                $cancelUrl
            );
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('cart_view');
        }

        return $this->redirect($checkoutUrl);
    }

    #[Route('/cart/payment-success', name: 'cart_payment_success', methods: ['GET'])]
    public function paymentSuccess(SessionInterface $session): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $cart = $this->normalizeCart($session->get('cart', []));
        if (empty($cart)) {
            $this->addFlash('warning', 'Panier deja valide ou vide.');
            return $this->redirectToRoute('cart_view');
        }

        $ids = array_map('intval', array_keys($cart));
        $equipements = $this->equipRepo->findBy(['id' => $ids]);
        $equipementsById = [];
        foreach ($equipements as $equipement) {
            $eid = $equipement->getId();
            if ($eid !== null) {
                $equipementsById[$eid] = $equipement;
            }
        }

        // Revalidate stock at payment completion time.
        foreach ($cart as $id => $qty) {
            $id = (int) $id;
            $qty = max(1, (int) $qty);
            $equip = $equipementsById[$id] ?? null;

            if (!$equip) {
                $this->addFlash('error', sprintf("L'equipement #%d n'existe plus.", $id));
                return $this->redirectToRoute('cart_view');
            }

            $available = max(0, (int) ($equip->getQuantiteDisponible() ?? 0));
            if ($available < $qty) {
                $this->addFlash(
                    'error',
                    sprintf(
                        'Stock insuffisant pour %s apres paiement. Contactez le support.',
                        $equip->getNom() ?? ('#' . $id)
                    )
                );
                return $this->redirectToRoute('cart_view');
            }
        }

        $user = $this->getUser();

        $commande = new Commande();
        $commande->setUtilisateur($user);
        $commande->setDateCommande(new \DateTime());
        $commande->setStatutCommande(Commande::STATUT_EN_ATTENTE);

        $total = '0.00';
        foreach ($cart as $id => $qty) {
            $id = (int) $id;
            $qty = max(1, (int) $qty);
            $equip = $equipementsById[$id] ?? null;
            if (!$equip) {
                continue;
            }

            $commande->addEquipement($equip);
            $lineTotal = bcmul((string) ($equip->getPrix() ?? '0.00'), (string) $qty, 2);
            $total = bcadd($total, $lineTotal, 2);

            $previousStatus = $equip->getStatut();
            $newQty = max(0, (int) ($equip->getQuantiteDisponible() ?? 0) - $qty);
            $equip->setQuantiteDisponible($newQty);
            if ($newQty === 0) {
                $equip->setStatut('en_rupture');
                if ($previousStatus !== 'en_rupture') {
                    $this->twilioSmsService->sendStockOutAlert($equip);
                }
            }
        }

        $commande->setMontantTotal($total);

        $this->em->persist($commande);
        $this->em->flush();

        $session->remove('cart');

        $this->addFlash('success', 'Paiement confirme. Commande creee avec succes.');

        return $this->redirectToRoute('cart_view');
    }

    #[Route('/cart/payment-cancel', name: 'cart_payment_cancel', methods: ['GET'])]
    public function paymentCancel(): Response
    {
        $this->addFlash('warning', 'Paiement annule. Votre panier est conserve.');
        return $this->redirectToRoute('cart_view');
    }

    private function normalizeCart(array $cart): array
    {
        if ($cart === []) {
            return [];
        }

        // Legacy format: [2, 5, 9] => [2 => 1, 5 => 1, 9 => 1]
        if (array_is_list($cart)) {
            $normalized = [];
            foreach ($cart as $id) {
                $key = (int) $id;
                if ($key > 0) {
                    $normalized[$key] = 1;
                }
            }

            return $normalized;
        }

        $normalized = [];
        foreach ($cart as $id => $qty) {
            $key = (int) $id;
            if ($key <= 0) {
                continue;
            }
            $normalized[$key] = max(1, (int) $qty);
        }

        return $normalized;
    }
}
