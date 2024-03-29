<?php

namespace App\Controller;

use App\Entity\User;

use App\Entity\Product;
use App\Entity\Customer;

use OpenApi\Attributes as OA;
use App\Services\ErrorValidator;

use App\Repository\UserRepository;
use App\Services\VersioningService;
use App\Services\UpdateEntitiesService;

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
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use JMS\Serializer\SerializerInterface as JmsSerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserController extends AbstractController
{
    private $userRepository;
    private $serializerInterface;
    private $tagCache;
    private $jmsSerializer;
    private $validator;
    private $em;
    private $router;
    private $updateEntity;
    private $checkForDuplicate;

    public function __construct(
        UserRepository $userRepository,
        SerializerInterface $serializerInterface,
        TagAwareCacheInterface $tagCache,
        JmsSerializerInterface $jmsSerializer,
        ErrorValidator $validator,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGeneratorInterface,
        UpdateEntitiesService $updateEntity,
        DuplicateCheckingService $checkForDuplicate
    ) {
        $this->userRepository = $userRepository;
        $this->serializerInterface = $serializerInterface;
        $this->tagCache = $tagCache;
        $this->jmsSerializer = $jmsSerializer;
        $this->validator = $validator;
        $this->em = $em;
        $this->router = $urlGeneratorInterface;
        $this->updateEntity = $updateEntity;
        $this->checkForDuplicate = $checkForDuplicate;
    }



    /* #region GET Users */
    /**
     * GET method to get all users
     */
    /* #region Doc */
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: User::class))
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized',
        content: new OA\JsonContent(
            example: [
                'message' => 'JWT Token not found'
            ]
        )

    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden',
        content: new OA\JsonContent(
            example: [
                'message' => 'You are not allowed to access this resource'
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(
            example: [
                'message' => 'No Users found'
            ]
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
        description: 'The number of user per page',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Tag(name: 'User')]
    /* #endregion */
    #[Route('/api/user', name: 'app_user', methods: 'GET')]
    #[IsGranted('ROLE_USER', message: 'You are not allowed to access this resource')]
    public function getAllUsers(Request $request, JmsSerializerInterface $jmsSerializer, VersioningService $versioningService): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);
        $version = $versioningService->getVersion();

        $idCache = 'user_' . $page . '_' . $limit;
        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles())) {
            $jsonUserList = $this->tagCache->get($idCache, function (ItemInterface $item) use ($page, $limit, $jmsSerializer, $version) {
                echo ("NO_CACHE_FOR_THIS_PAGE_OF_USERS");
                $context = SerializationContext::create()->setGroups(['user', 'product']);
                $context->setVersion($version);
                $item->tag('userCache');
                $userList = $this->userRepository->findByCustomerWithPagination($page, $limit, $this->getUser());
                return $jmsSerializer->serialize(
                    $userList,
                    'json',
                    $context
                );
            });
        } else {
            $jsonUserList = $this->tagCache->get($idCache, function (ItemInterface $item) use ($page, $limit, $jmsSerializer, $version) {
                echo ("NO_CACHE_FOR_THIS_PAGE_OF_USERS");
                $context = SerializationContext::create()->setGroups(['customer', 'user', 'product']);
                $context->setVersion($version);
                $item->tag('userCache');
                $userList = $this->userRepository->findAllWithPagination($page, $limit);
                return $jmsSerializer->serialize(
                    $userList,
                    'json',
                    $context
                );
            });
        }

        return new JsonResponse($jsonUserList, 200, [], true);
    }
    /* #endregion */

    /* #region GET One User */
    /**
     * GET method to get one user
     */
    /* #region Doc */
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: User::class))
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized',
        content: new OA\JsonContent(
            example: [
                'message' => 'JWT Token not found'
            ]
        )

    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden',
        content: new OA\JsonContent(
            example: [
                'message' => 'You are not allowed to access this resource'
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(
            example: [
                'message' => 'User not found'
            ]
        )
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'The user id',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Tag(name: 'User')]
    /* #endregion */
    #[Route('/api/user/{id}', name: 'app_detail_user', methods: 'GET')]
    #[IsGranted('ROLE_USER', message: 'You are not allowed to access this resource')]
    public function getOneCustomer(User $user, VersioningService $versioningService): JsonResponse
    {

        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles())) {
            // We only return the user if he is the user of the customer
            if ($user->getCustomer() != $this->getUser()) {
                return new JsonResponse(['message' => 'You are not allowed to access this resource'], Response::HTTP_FORBIDDEN);
            }

            $version = $versioningService->getVersion();
            $context = SerializationContext::create()->setGroups(['user', 'product']);
            $context->setVersion($version);
            $jsonUser = $this->jmsSerializer->serialize($user, 'json', $context);
            return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);

            // We clear the cache
            $this->tagCache->invalidateTags(['userCache']);
        } else {

            $version = $versioningService->getVersion();
            $context = SerializationContext::create()->setGroups(['customer', 'user', 'product']);
            $context->setVersion($version);
            $jsonUser = $this->jmsSerializer->serialize($user, 'json', $context);
            return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);

            // We clear the cache
            $this->tagCache->invalidateTags(['userCache']);
        }
    }
    /* #endregion */

    /* #region POST a User */
    /**
     * POST method to create a user
     */
    /* #region Doc */
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: User::class))
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized',
        content: new OA\JsonContent(
            example: [
                'message' => 'JWT Token not found'
            ]
        )

    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden',
        content: new OA\JsonContent(
            example: [
                'message' => 'You are not allowed to access this resource'
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(
            example: [
                'message' => 'Something went wrong, User not created'
            ]
        )
    )]
    #[OA\RequestBody(
        description: 'User object that needs to be added to the store',
        required: true,
        content: new OA\JsonContent(
            ref: new Model(
                type: User::class
            ),
            title: 'New user',
            description: 'Replace the customer and the products values by real values',
            example: [
                'email' => 'email',
                'password' => 'password',
                'username' => 'username',
                'customer' => [1],
                'products' => [1, 2]
            ]
        )
    )]
    #[OA\Tag(name: 'User')]
    /* #endregion */
    #[Route('/api/user', name: 'app_create_user', methods: 'POST')]
    #[IsGranted('ROLE_USER', message: 'You are not allowed to access this resource')]
    public function createUser(Request $request, UserPasswordHasherInterface $encoder): JsonResponse
    {
        /*----------------------------------
        | We create the user
        -----------------------------------*/
        $user = $this->serializerInterface->deserialize($request->getContent(), User::class, 'json');

        /*----------------------------------
        | We check if this email is already used
        -----------------------------------*/
        $checkForDuplicate = $this->checkForDuplicate->checkForExistingEntry(User::class, 'email', $user->getEmail());
        if ($checkForDuplicate) {
            return $checkForDuplicate;
        }

        /*----------------------------------
        | We manage the password
        -----------------------------------*/
        $password = $user->getPassword();
        $user->setPassword($encoder->hashPassword($user, $password));

        /*----------------------------------
        | We manage the role
        -----------------------------------*/
        $role = $user->getRoles();
        $user->setRoles($role);

        /*----------------------------------
        | We affect this user to a customer
        -----------------------------------*/
        $datas = $request->toArray();
        $customerId = $datas['customer'][0];
        $customer = $this->em->getRepository(Customer::class)->findBy(['id' => $customerId]);
        if (!$customer) {
            return new JsonResponse(['message' => 'This customer does not exist'], Response::HTTP_BAD_REQUEST);
        }
        $user->setCustomer($customer[0]);

        /*----------------------------------
        | We manage the Products Collection
        -----------------------------------*/
        // We empty the user products collection
        $user->getProducts()->clear();
        // We get the arrays from the request
        $context = $request->toArray();
        // We check if there are Products affected to this new User
        $products = isset($context['products']) ? $context['products'] : null;
        // We loop the array to get the Product based on his id and add it to the User's Products collection.
        if ($products !== null) {
            foreach ($products as $product) {
                $product = $this->em->getRepository(Product::class)->findBy(['id' => $product]);
                $user->addProduct($product[0]);
            }
        }

        /*----------------------------------
        | We check if there are any errors
        -----------------------------------*/
        $this->validator->getErrors($user);

        /*----------------------------------
        | We persist the customer
        -----------------------------------*/
        $this->em->persist($user);
        $this->em->flush();

        /*----------------------------------
        | We prepare the response
        -----------------------------------*/
        $context = SerializationContext::create()->setGroups(['user']);
        $jsonUser = $this->jmsSerializer->serialize($user, 'json', $context);

        $location = $this->router->generate('app_detail_user', ['id' => $user->getId()]);

        // We clear the cache
        $this->tagCache->invalidateTags(['customerCache']);

        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ['Location' => $location], true);
    }
    /* #endregion */

    /* #region PUT a User */
    /**
     * PUT method to update a user
     */
    /* #region Doc */
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: User::class))
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized',
        content: new OA\JsonContent(
            example: [
                'message' => 'JWT Token not found'
            ]
        )

    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden',
        content: new OA\JsonContent(
            example: [
                'message' => 'You are not allowed to access this resource'
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(
            example: [
                'message' => 'Something went wrong, User not updated'
            ]
        )
    )]
    #[OA\RequestBody(
        description: 'User object that needs to be updated to the store',
        required: true,
        content: new OA\JsonContent(
            title: 'Updated user',
            description: 'Replace the customer and the products values by real values',
            example: [
                'email' => 'email',
                'password' => 'password',
                'username' => 'username',
                'customer' => [1],
                'products' => [1, 2]
            ],
            ref: new Model(type: User::class)
        )
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'The user id',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Tag(name: 'User')]
    /* #endregion */
    #[Route('/api/user/{id}', name: 'app_update_user', methods: 'PUT')]
    #[IsGranted('ROLE_USER', message: 'You are not allowed to access this resource')]
    public function updateCustomer(User $user, Request $request): JsonResponse
    {

        $newUser = $this->serializerInterface->deserialize($request->getContent(), User::class, 'json');
        // We go through the new customer's properties and check if there is something to update
        $update = $this->updateEntity->update($user, $newUser, $request);
        if ($update) {
            return $update;
        }

        // We check if there are errors
        $this->validator->getErrors($user);

        // We persist the updated informations
        $this->em->persist($user);
        $this->em->flush();

        // We prepare the Response
        $context = SerializationContext::create()->setGroups(['user']);
        $jsonUser = $this->jmsSerializer->serialize($user, 'json', $context);

        $location = $this->router->generate('app_detail_user', ['id' => $user->getId()]);

        // We clear the cache
        $this->tagCache->invalidateTags(['customerCache']);

        return new JsonResponse($jsonUser, Response::HTTP_OK, ['Location' => $location], true);
    }
    /* #endregion */

    /* #region DELETE a User */
    /**
     * DELETE method to delete a customer
     */
    /* #region Doc */
    #[OA\Response(
        response: 204,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'status' => 'User deleted'
            ],
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized',
        content: new OA\JsonContent(
            example: [
                'message' => 'JWT Token not found'
            ]
        )

    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden',
        content: new OA\JsonContent(
            example: [
                'message' => 'You are not allowed to access this resource'
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(
            example: [
                'message' => 'Something went wrong, User not deleted'
            ]
        )
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'The user id',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Tag(name: 'User')]
    /* #endregion */
    #[Route('/api/user/{id}', name: 'app_delete_user', methods: 'DELETE')]
    #[IsGranted('ROLE_USER', message: 'You are not allowed to access this resource')]
    public function deleteUser(User $user): JsonResponse
    {
        $userId = $user->getId();
        $this->tagCache->invalidateTags(['userCache']);
        $this->em->remove($user);
        $this->em->flush();

        // Return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        return new JsonResponse(['status' => 'User' . $userId .  'deleted'], Response::HTTP_NO_CONTENT);
    }
    /* #endregion */
}
