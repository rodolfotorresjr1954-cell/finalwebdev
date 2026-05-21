<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Service\ActivityLogService;
use App\Service\ProductMenuCategoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Form\FormError;

#[Route('/product')]
final class ProductController extends AbstractController
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {
    }
    #[Route(name: 'app_product_index', methods: ['GET'])]
    public function index(Request $request, ProductRepository $productRepository, CategoryRepository $categoryRepository): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $categoryId = $request->query->getInt('category');
        $sort = (string) $request->query->get('sort', 'name');
        // Name sort is always A→Z on the catalog UI (no sort dropdown)
        $dir = $sort === 'name'
            ? 'ASC'
            : (strtolower((string) $request->query->get('dir', 'asc')) === 'desc' ? 'DESC' : 'ASC');

        $qb = $productRepository->createQueryBuilder('p')
            ->leftJoin('p.Category', 'c')->addSelect('c')
            ->leftJoin('p.createdBy', 'u')->addSelect('u');

        if ($search !== '') {
            $qb->andWhere('LOWER(p.Name) LIKE :search OR LOWER(p.Description) LIKE :search')
               ->setParameter('search', '%' . strtolower($search) . '%');
        }

        if ($categoryId > 0) {
            $qb->andWhere('p.Category = :categoryId')
               ->setParameter('categoryId', $categoryId);
        }

        switch ($sort) {
            case 'price':
                $qb->orderBy('p.Price', $dir);
                break;
            case 'date':
                $qb->orderBy('p.Datetime', $dir);
                break;
            case 'name':
            default:
                $qb->orderBy('p.Name', $dir);
                break;
        }

        $products = $qb->getQuery()->getResult();
        $categories = $categoryRepository->findAll();

        return $this->render('product/index.html.twig', [
            'products' => $products,
            'categories' => $categories,
            'search' => $search,
            'categoryId' => $categoryId,
            'sort' => $sort,
        ]);
    }

    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        ProductMenuCategoryService $productMenuCategoryService,
        CategoryRepository $categoryRepository,
    ): Response {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->remove('datetime');
        $form->handleRequest($request);

        $nameCategoryMap = $this->buildProductNameCategoryMap($productMenuCategoryService, $categoryRepository);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyCategoryFromProductName($product, $productMenuCategoryService, $categoryRepository);

            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);

                $imageValid = true;

                // Validate size (2MB)
                $maxBytes = 2 * 1024 * 1024;
                if ($imageFile->getSize() > $maxBytes) {
                    $imageValid = false;
                    $form->get('image')->addError(new FormError('Image must be 2MB or less.'));
                }

                // Validate extension based on original filename (no MIME guessers required)
                $extension = strtolower((string) $imageFile->getClientOriginalExtension());
                $allowed = ['jpg', 'jpeg', 'png'];
                if ($extension === '' || !in_array($extension, $allowed, true)) {
                    $imageValid = false;
                    $form->get('image')->addError(new FormError('Please upload a valid image (jpg or png).'));
                }

                if (!$imageValid) {
                    return $this->render('product/new.html.twig', [
                        'product' => $product,
                        'form' => $form,
                    ]);
                }

                $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;
                $imageFile->move($this->getParameter('images_directory'), $newFilename);
                $product->setImage($newFilename);
            }


            $product->setDatetime(new \DateTimeImmutable());
            $user = $this->getUser();
            if ($user instanceof \App\Entity\User) {
                $product->setCreatedBy($user);
            }

            $entityManager->persist($product);
            $entityManager->flush();

            if ($user instanceof \App\Entity\User) {
                $this->activityLogService->logCreate(
                    $user,
                    'Product',
                    $product->getId(),
                    ['name' => $product->getName()],
                    sprintf('Created product: %s', $product->getName())
                );
            }
            
            $this->addFlash('success', 'Product created successfully.');

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
            'nameCategoryMap' => $nameCategoryMap,
        ]);
    }

    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Product $product,
        EntityManagerInterface $entityManager,
        ProductMenuCategoryService $productMenuCategoryService,
        CategoryRepository $categoryRepository,
    ): Response {
        $this->denyUnlessOwnerOrAdmin($product);

        $form = $this->createForm(ProductType::class, $product);
        $form->remove('datetime');
        $form->handleRequest($request);

        $nameCategoryMap = $this->buildProductNameCategoryMap($productMenuCategoryService, $categoryRepository);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyCategoryFromProductName($product, $productMenuCategoryService, $categoryRepository);
            $entityManager->flush();

            $user = $this->getUser();
            if ($user instanceof \App\Entity\User) {
                $this->activityLogService->logUpdate(
                    $user,
                    'Product',
                    $product->getId(),
                    ['name' => $product->getName()],
                    sprintf('Updated product: %s', $product->getName())
                );
            }

            $this->addFlash('success', 'Product updated successfully.');

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
            'nameCategoryMap' => $nameCategoryMap,
        ]);
    }

    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->getString('_token'))) {
            $this->denyUnlessOwnerOrAdmin($product);

            $productName = $product->getName();
            $productId = $product->getId();
            
            $entityManager->remove($product);
            $entityManager->flush();

            $user = $this->getUser();
            if ($user instanceof \App\Entity\User) {
                $this->activityLogService->logDelete(
                    $user,
                    'Product',
                    $productId,
                    ['name' => $productName],
                    sprintf('Deleted product: %s', $productName)
                );
            }
            
            $this->addFlash('success', 'Product deleted successfully.');
        }

        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }

    private function denyUnlessOwnerOrAdmin(Product $product): void
    {
        $user = $this->getUser();
        if ($user instanceof \App\Entity\User && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        if ($user instanceof \App\Entity\User && $product->getCreatedBy()?->getId() === $user->getId()) {
            return;
        }

        throw new AccessDeniedException('You cannot modify this record.');
    }

    /**
     * @return array<string, int|null> preset title => category id or null if no DB category matches
     */
    private function buildProductNameCategoryMap(
        ProductMenuCategoryService $productMenuCategoryService,
        CategoryRepository $categoryRepository,
    ): array {
        $map = [];
        foreach (ProductType::PRESET_NAME_CHOICES as $title) {
            $labels = $productMenuCategoryService->inferLabelsFromTitle($title);
            $canonical = $labels[0] ?? 'Other';
            $cat = in_array($canonical, ['Burger', 'Fries', 'Drinks'], true)
                ? $categoryRepository->findBestMatchForMenuLabel($canonical)
                : null;
            $map[$title] = $cat?->getId();
        }

        return $map;
    }

    private function applyCategoryFromProductName(
        Product $product,
        ProductMenuCategoryService $productMenuCategoryService,
        CategoryRepository $categoryRepository,
    ): void {
        $labels = $productMenuCategoryService->inferLabelsFromTitle($product->getName() ?? '');
        $canonical = $labels[0] ?? 'Other';
        if (!in_array($canonical, ['Burger', 'Fries', 'Drinks'], true)) {
            return;
        }
        $cat = $categoryRepository->findBestMatchForMenuLabel($canonical);
        if ($cat !== null) {
            $product->setCategory($cat);
        }
    }
}
