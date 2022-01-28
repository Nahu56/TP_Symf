<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\ProductRepository;
use App\Repository\CommandRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\Command;
use App\Form\CommandType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;


class Controller extends AbstractController
{

    /**
     * @Route("/", name="accueil")
     */
    public function accueil(ProductRepository $product): Response
    {
        $prodCheap = $product->findBy(
            [],
            ['price' => 'ASC'],
            5
        );
        $prodRecents = $product->findBy(
            [],
            ['createdAt' => "DESC"],
            5
        );

        return $this->render('/accueil.html.twig', [
            'controller_name' => 'Controller',
            'prodRecents' => $prodRecents,
            'prodCheap' => $prodCheap
        ]);
    }

    /**
     * @Route("/product", name="product")
     */
    public function listeProduits(ProductRepository $product): Response
    {
        $result = $product->findAll();


        return $this->render('/listProducts.html.twig', [
            'controller_name' => 'Controller',
            'products' => $result
        ]);
    }

    /**
     * @Route("/product/{id}", name="product.show")
     */
    public function pageProduit($id, ProductRepository $product): Response
    {
        $result = $product->find($id);

        return $this->render('/product.html.twig', [
            'controller_name' => 'Controller',
            'products' => $result
        ]);
    }

    /**
     * @Route("/cart/add/{id}", name="ajoutPanier")
     */
    public function ajoutPanier($id, SessionInterface $session, ProductRepository $product)
    {
        $cart = $session->get('panier', []);
        $result = $product->find($id);


        if(!isset($result)){            
            return $this->json(['message' => 'nok'], 404);
        }else{
            $cart[$id] = 1;
            $session->set('panier', $cart);

            return $this->json(['message' => 'ok'], 200);
        }
    }

    /**
     * @Route("/cart", name="cart")
     */
    public function panier(ProductRepository $productRepository, SessionInterface $session, EntityManagerInterface $manager, Request $request): Response
    {
        $cartProducts = $session->get('panier', []);
        // $totalProducts = $product->findAll();
        $totalPanier = 0;

        $infosProduit = [];

        foreach($cartProducts as $key => $product){
            $infos[$key] = $productRepository->find($key);

            $infosProduit[$key]['id'] = $key;
            $infosProduit[$key]['nom'] = $infos[$key]->getName();
            $infosProduit[$key]['prix'] = $infos[$key]->getPrice();
            $infosProduit[$key]['quantite'] = 1;
        }

        foreach($infosProduit as $produit){
            $totalPanier += $produit['prix'];
        }

        $command = new Command(); 
        $commandForm = $this->createForm(CommandType::class, $command);
        $commandForm->handleRequest($request);

        if ($commandForm->isSubmitted() && $commandForm->isValid()){

            $command->setCreatedAt(new \Datetime);

            foreach($cartProducts as $key => $product){
                $prod = $productRepository->find($key);

                $command->addProduct($prod);

                unset($cartProducts[$key]);
            }

            $session->set('panier', $cartProducts);
            $manager->persist($command);

            $manager->flush();

            $this->addFlash('alert', "La commande à bien été ajoutée");
            return $this->redirectToRoute("cart");
        }

        return $this->render('/panier.html.twig', [
            'controller_name' => 'Controller',
            'products' => $infosProduit,
            'totalPanier' => $totalPanier,
            'commandForm' => $commandForm->createView()
        ]);
    }

    /**
     * @Route("/cart/delete/{id}", name="supprPanier")
     */
    public function supprPanier($id, SessionInterface $session): Response
    {
        $cart = $session->get('panier', []);
        if(!isset($cart[$id])){
            $this->addFlash('alert-error', "Le produit n'est pas présent dans le panier");
            return $this->redirectToRoute('cart');
        }

        unset($cart[$id]);
        $session->set('panier', $cart);

        return $this->redirectToRoute('cart');
    }

    /**
     * @Route("/command", name="listeCommandes")
     */
    public function listeCommandes(SessionInterface $session, CommandRepository $command): Response
    {
        $result = $command->findAll();

        return $this->render('/commandes.html.twig', [
            'controller_name' => 'Controller',
            "commandes" => $result
        ]);
    }

    /**
     * @Route("/command/{id}", name="commande")
     */
    public function commandes($id, SessionInterface $session, CommandRepository $command): Response
    {
        $produitsCommande = $command->find($id);
        $totalPanier = 0;

        $produits = $produitsCommande->getProducts()->toArray();

        foreach($produits as $produit){
            $totalPanier += $produit->getPrice();
        }

        return $this->render('/commande.html.twig', [
            'controller_name' => 'Controller',
            'produitsCommande' => $produitsCommande,
            'produits' => $produits,
            'totalPanier' => $totalPanier

        ]); 
    }
}
