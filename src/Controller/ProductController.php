<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Product;
use App\Services\DuplicateCheckingService;
use App\Services\UpdateEntitiesService;

use Nelmio\ApiDocBundle\Annotation\Model;

use Doctrine\ORM\EntityManagerInterface;

use JMS\Serializer\SerializerInterface as JmsSerializerInterface;
use JMS\Serializer\SerializationContext;

use OpenApi\Attributes as OA;
use App\Services\ErrorValidator;

use App\Services\VersioningService;
use App\Repository\ProductRepository;

use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ProductController extends AbstractController
{
    private $productRepository;
    private $serializerInterface;
    private $tagCache;
    private $jmsSerializer;
    private $validator;
    private $em;
    private $router;
    private $updateEntity;
    private $checkForDuplicate;

    public function __construct(
        ProductRepository $productRepository,
        SerializerInterface $serializerInterface,
        JmsSerializerInterface $jmsSerializer,
        TagAwareCacheInterface $tagCache,
        ErrorValidator $validator,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGeneratorInterface,
        UpdateEntitiesService $updateEntity,
        DuplicateCheckingService $checkForDuplicate
    ) {
        $this->productRepository = $productRepository;
        $this->serializerInterface = $serializerInterface;
        $this->tagCache = $tagCache;
        $this->validator = $validator;
        $this->em = $em;
        $this->router = $urlGeneratorInterface;
        $this->updateEntity = $updateEntity;
        $this->checkForDuplicate = $checkForDuplicate;
        $this->jmsSerializer = $jmsSerializer;
    }



    /* #region GET Products */
    /**
     * GET method to get all products
     */
    /* #region Doc */
    #[OA\Response(
        response: 200,
        description: 'Return all products',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Product::class))
        )
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'The page number',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'The number of products per page',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Tag(name: 'Product')]
    /* #endregion */
    #[Route('/api/product', name: 'app_product', methods: 'GET')]
    public function getAllProducts(Request $request, JmsSerializerInterface $jmsSerializer): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);

        $idCache = 'product_' . $page . '_' . $limit;

        $jsonProductsList = $this->tagCache->get($idCache, function (ItemInterface $item) use ($page, $limit, $jmsSerializer) {
            echo ("NO_CACHE_FOR_THIS_PAGE_OF_PRODUCTS");
            $context = SerializationContext::create()->setGroups(['admin', 'user', 'guest']);
            $item->tag('productCache');
            $productList = $this->productRepository->findAllWithPagination($page, $limit);
            return $jmsSerializer->serialize(
                $productList,
                'json',
                $context
            );
        });

        return new JsonResponse($jsonProductsList, 200, [], true);
    }
    /* #endregion */

    /* #region GET One Product */
    /**
     * GET method to get one product
     */
    /* #region Doc */
    #[OA\Response(
        response: 200,
        description: 'Return one product',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Product::class))
        )
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'The product id',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Tag(name: 'Product')]
    /* #endregion */
    #[Route('/api/product/{id}', name: 'app_detail_product', methods: 'GET')]
    public function getOneProduct(Product $product, VersioningService $versioningService): JsonResponse
    {

        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(['admin', 'user', 'guest']);
        $context->setVersion($version);
        $jsonProduct = $this->jmsSerializer->serialize($product, 'json', $context);

        return new JsonResponse($jsonProduct, Response::HTTP_OK, [], true);
    }
    /* #endregion */

    /* #region POST a Product */
    /**
     * POST method to create a product
     */
    /* #region Doc */
    #[OA\Response(
        response: 201,
        description: 'Create a product',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Product::class))
        )
    )]
    #[OA\RequestBody(
        description: 'Product object that needs to be added to the db',
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: Product::class)
        )
    )]
    #[OA\Tag(name: 'Product')]
    /* #endregion */
    #[IsGranted('ROLE_USER', message: 'You are not allowed to access this resource')]
    #[IsGranted('ROLE_ADMIN', message: 'You are not allowed to access this resource')]
    #[Route('/api/product', name: 'app_create_product', methods: 'POST')]
    public function createProduct(Request $request): JsonResponse
    {
        /*----------------------------------
        | We create the product
        -----------------------------------*/
        $product = $this->serializerInterface->deserialize($request->getContent(), Product::class, 'json');

        /*----------------------------------
        | We check if this product name is already used
        -----------------------------------*/
        $checkForDuplicate = $this->checkForDuplicate->checkForExistingEntry(Product::class, 'name', $product->getName());
        if ($checkForDuplicate) {
            return $checkForDuplicate;
        }

        /*----------------------------------
        | We affect this product to user from the array if not empty
        -----------------------------------*/
        $context = $request->toArray();
        $userList = isset($context['owner']) ? $context['owner'] : null;
        if ($userList != null) {
            $product->getOwner()->clear();
            foreach ($userList as $userId) {
                $user = $this->em->getRepository(User::class)->findBy(['id' => $userId]);
                if (!$user) {
                    return new JsonResponse(['message' => 'This user does not exist'], Response::HTTP_BAD_REQUEST);
                }
                $product->addOwner($user[0]);
            }
        }

        /*----------------------------------
        | We check if there are any errors
        -----------------------------------*/
        $this->validator->getErrors($product);

        /*----------------------------------
        | We persist the customer
        -----------------------------------*/
        $this->em->persist($product);
        $this->em->flush();

        /*----------------------------------
        | We prepare the response
        -----------------------------------*/
        $context = SerializationContext::create()->setGroups(['guest']);
        $jsonProduct = $this->jmsSerializer->serialize($product, 'json', $context);

        $location = $this->router->generate('app_detail_product', ['id' => $product->getId()]);

        return new JsonResponse($jsonProduct, Response::HTTP_CREATED, ['Location' => $location], true);
    }
    /* #endregion */

    /* #region PUT a User */
    /**
     * PUT method to update a user
     */
    /* #region Doc */
    #[OA\Response(
        response: 200,
        description: 'Update a product',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Product::class))
        )
    )]
    #[OA\RequestBody(
        description: 'Product object that needs to be updated to the db',
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: Product::class)
        )
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'The product id',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Tag(name: 'Product')]
    /* #endregion */
    #[Route('/api/product/{id}', name: 'app_update_product', methods: 'PUT')]
    #[IsGranted('ROLE_USER', message: 'You are not allowed to access this resource')]
    #[IsGranted('ROLE_ADMIN', message: 'You are not allowed to access this resource')]
    public function updateProduct(Product $product, Request $request): JsonResponse
    {

        $newProduct = $this->serializerInterface->deserialize($request->getContent(), Product::class, 'json');
        // We go through the new customer's properties and check if there is something to update
        $update = $this->updateEntity->update($product, $newProduct, $request);
        if ($update) {
            return $update;
        }

        // We check if there are errors
        $this->validator->getErrors($product);

        // We persist the updated informations
        $this->em->persist($product);
        $this->em->flush();

        // We prepare the Response
        $context = SerializationContext::create()->setGroups(['guest']);
        $jsonProduct = $this->jmsSerializer->serialize($product, 'json', $context);

        $location = $this->router->generate('app_detail_product', ['id' => $product->getId()]);

        return new JsonResponse($jsonProduct, Response::HTTP_OK, ['Location' => $location], true);

        // We clear the cache
        $this->tagCache->invalidateTags(['productCache']);
    }
    /* #endregion */

    /* #region DELETE a Product */
    /**
     * DELETE method to delete a product
     */
    /* #region Doc */
    #[OA\Response(
        response: 200,
        description: 'Delete a product',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Product::class))
        )
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'The product id',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Tag(name: 'Product')]
    /* #endregion */
    #[IsGranted('ROLE_USER', message: 'You are not allowed to access this resource')]
    #[IsGranted('ROLE_ADMIN', message: 'You are not allowed to access this resource')]
    #[Route('/api/product/{id}', name: 'app_delete_product', methods: 'DELETE')]
    public function deleteUser(Product $product): JsonResponse
    {
        $productId = $product->getId();
        $this->tagCache->invalidateTags(['productCache']);
        $this->em->remove($product);
        $this->em->flush();

        // Return new JsonResponse(null, Response::HTTP_NO_CONTENT)
        return new JsonResponse(['status' => 'Product ' . $productId . ' deleted'], Response::HTTP_OK);
    }
    /* #endregion */
}
