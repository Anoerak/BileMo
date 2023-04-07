<?php

namespace App\Controller;

use App\Entity\User;

use App\Entity\Product;

use OpenApi\Attributes as OA;
use App\Services\ErrorValidator;

use App\Services\VersioningService;
use App\Repository\ProductRepository;

use App\Services\UpdateEntitiesService;

use Symfony\Config\JmsSerializerConfig;
use Doctrine\ORM\EntityManagerInterface;

use JMS\Serializer\SerializationContext;

use Nelmio\ApiDocBundle\Annotation\Model;
use App\Services\DuplicateCheckingService;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use JMS\Serializer\SerializerInterface as JmsSerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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
            items: new OA\Items(ref: new Model(type: Product::class, groups: ['guest']))
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
    /* #endregion */
    #[Route('/api/product', name: 'app_product', methods: 'GET')]
    public function getAllProducts(Request $request, JmsSerializerInterface $jmsSerializer): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);

        $idCache = 'product_' . $page . '_' . $limit;

        $jsonProductsList = $this->tagCache->get($idCache, function (ItemInterface $item) use ($page, $limit, $jmsSerializer) {
            echo ("NO_CACHE_FOR_THIS_PAGE_OF_PRODUCTS");
            $context = SerializationContext::create()->setGroups(['guest']);
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

    /* #region GET One User */
    /**
     * GET method to get one user
     */
    /* #region Doc */
    #[OA\Response(
        response: 200,
        description: 'Return one product',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Product::class, groups: ['guest']))
        )
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'The product id',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    /* #endregion */
    #[Route('/api/product/{id}', name: 'app_detail_product', methods: 'GET')]
    public function getOneProduct(Product $product, VersioningService $versioningService): JsonResponse
    {

        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(['guest']);
        $context->setVersion($version);
        $jsonProduct = $this->jmsSerializer->serialize($product, 'json', $context);

        return new JsonResponse($jsonProduct, Response::HTTP_OK, [], true);
    }
    /* #endregion */

    /* #region POST a User */
    /**
     * POST method to create a user
     */
    /* #region Doc */
    #[OA\Response(
        response: 201,
        description: 'Create a product',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Product::class, groups: ['guest']))
        )
    )]
    #[OA\RequestBody(
        description: 'Product object that needs to be added to the db',
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: Product::class, groups: ['guest'])
        )
    )]
    /* #endregion */
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

        dump($product);
        return new JsonResponse($jsonProduct, Response::HTTP_CREATED, ['Location' => $location], true);
    }
    /* #endregion */

    // /* #region PUT a User */
    // /**
    //  * PUT method to update a user
    //  */
    // /* #region Doc */
    // #[OA\Response(
    //     response: 200,
    //     description: 'Update a user',
    //     content: new OA\JsonContent(
    //         type: 'array',
    //         items: new OA\Items(ref: new Model(type: User::class, groups: ['user:read']))
    //     )
    // )]
    // #[OA\RequestBody(
    //     description: 'User object that needs to be updated to the store',
    //     required: true,
    //     content: new OA\JsonContent(
    //         ref: new Model(type: User::class, groups: ['user:write'])
    //     )
    // )]
    // #[OA\Parameter(
    //     name: 'id',
    //     in: 'path',
    //     description: 'The user id',
    //     required: true,
    //     schema: new OA\Schema(type: 'integer')
    // )]
    // /* #endregion */
    // #[Route('/api/user/{id}', name: 'app_update_user', methods: 'PUT')]
    // #[IsGranted('ROLE_ADMIN', message: 'You are not allowed to access this resource')]
    // public function updateCustomer(User $user, Request $request): JsonResponse
    // {

    //     $newUser = $this->serializerInterface->deserialize($request->getContent(), User::class, 'json');
    //     // We go through the new customer's properties and check if there is something to update
    //     $update = $this->updateEntity->update($user, $newUser, $request);
    //     if ($update) {
    //         return $update;
    //     }

    //     // We check if there are errors
    //     $this->validator->getErrors($user);

    //     // We persist the updated informations
    //     $this->em->persist($user);
    //     $this->em->flush();

    //     // We prepare the Response
    //     $context = SerializationContext::create()->setGroups(['user:read']);
    //     $jsonUser = $this->jmsSerializer->serialize($user, 'json', $context);

    //     $location = $this->router->generate('app_detail_user', ['id' => $user->getId()]);

    //     return new JsonResponse($jsonUser, Response::HTTP_OK, ['Location' => $location], true);

    //     // We clear the cache
    //     $this->tagCache->invalidateTags(['customerCache']);
    // }
    // /* #endregion */

    // /* #region DELETE a User */
    // /**
    //  * DELETE method to delete a customer
    //  */
    // /* #region Doc */
    // #[OA\Response(
    //     response: 200,
    //     description: 'Delete a user',
    //     content: new OA\JsonContent(
    //         type: 'array',
    //         items: new OA\Items(ref: new Model(type: User::class, groups: ['user:read']))
    //     )
    // )]
    // #[OA\Parameter(
    //     name: 'id',
    //     in: 'path',
    //     description: 'The user id',
    //     required: true,
    //     schema: new OA\Schema(type: 'integer')
    // )]
    // /* #endregion */
    // #[Route('/api/user/{id}', name: 'app_delete_user', methods: 'DELETE')]
    // #[IsGranted('ROLE_ADMIN', message: 'You are not allowed to access this resource')]
    // public function deleteUser(User $user): JsonResponse
    // {
    //     $userId = $user->getId();
    //     $this->tagCache->invalidateTags(['userCache']);
    //     $this->em->remove($user);
    //     $this->em->flush();

    //     // Return new JsonResponse(null, Response::HTTP_NO_CONTENT)
    //     return new JsonResponse(['status' => 'User' . $userId . ' deleted'], Response::HTTP_OK);
    // }
    // /* #endregion */
}
