<?php

namespace App\Controller;

use OpenApi\Attributes as OA;

use App\Repository\CustomerRepository;

use Nelmio\ApiDocBundle\Annotation\Model;

use Doctrine\ORM\EntityManagerInterface;

use App\Entity\User;
use App\Entity\Customer;

use App\Services\ErrorValidator;
use App\Services\VersioningService;
use App\Services\UpdateEntitiesService;
use App\Services\DuplicateCheckingService;

use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface as JmsSerializerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CustomerController extends AbstractController
{
    private $customerRepository;
    private $serializerInterface;
    private $tagCache;
    private $jmsSerializer;
    private $validator;
    private $em;
    private $router;
    private $updateEntity;
    private $checkForDuplicate;

    public function __construct(
        CustomerRepository $customerRepository,
        SerializerInterface $serializerInterface,
        TagAwareCacheInterface $tagCache,
        JmsSerializerInterface $jmsSerializer,
        ErrorValidator $validator,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGeneratorInterface,
        UpdateEntitiesService $updateEntity,
        DuplicateCheckingService $checkForDuplicate
    ) {
        $this->customerRepository = $customerRepository;
        $this->serializerInterface = $serializerInterface;
        $this->tagCache = $tagCache;
        $this->jmsSerializer = $jmsSerializer;
        $this->validator = $validator;
        $this->em = $em;
        $this->router = $urlGeneratorInterface;
        $this->updateEntity = $updateEntity;
        $this->checkForDuplicate = $checkForDuplicate;
    }



    /* #region GET Customers */
    /**
     * GET method to get all customers
     */
    /* #region Doc */
    #[OA\Response(
        response: 200,
        description: 'Return all customers',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: ['admin']))
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
        description: 'The number of customer per page',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    /* #endregion */
    #[Route('/api/customer', name: 'app_customer', methods: 'GET')]
    #[IsGranted('ROLE_ADMIN', message: 'You are not allowed to access this resource')]
    public function getAllCustomers(Request $request, JmsSerializerInterface $jmsSerializer): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);

        $idCache = 'customer_' . $page . '_' . $limit;

        $jsonCustomerList = $this->tagCache->get($idCache, function (ItemInterface $item) use ($page, $limit, $jmsSerializer) {
            echo ("NO_CACHE_FOR_THIS_PAGE_OF_CUSTOMERS");
            $context = SerializationContext::create()->setGroups(['admin']);
            $item->tag('customerCache');
            $customerList = $this->customerRepository->findAllWithPagination($page, $limit);
            return $jmsSerializer->serialize(
                $customerList,
                'json',
                $context
                // ['groups' => 'admin']
            );
        });

        return new JsonResponse($jsonCustomerList, 200, [], true);
    }
    /* #endregion */

    /* #region GET One Customer */
    /**
     * GET method to get one customer
     */
    /* #region Doc */
    #[OA\Response(
        response: 200,
        description: 'Return one customer',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: ['admin']))
        )
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'The customer id',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    /* #endregion */
    #[Route('/api/customer/{id}', name: 'app_detail_customer', methods: 'GET')]
    #[IsGranted('ROLE_ADMIN', message: 'You are not allowed to access this resource')]
    public function getOneCustomer(Customer $customer, VersioningService $versioningService): JsonResponse
    {

        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(['admin']);
        $context->setVersion($version);
        $jsonCustomer = $this->jmsSerializer->serialize($customer, 'json', $context);

        return new JsonResponse($jsonCustomer, Response::HTTP_OK, [], true);
    }
    /* #endregion */

    /* #region POST a Customer */
    /**
     * POST method to create a customer
     */
    /* #region Doc */
    #[OA\Response(
        response: 201,
        description: 'Create a customer',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: ['admin']))
        )
    )]
    #[OA\RequestBody(
        description: 'Customer object that needs to be added to the store',
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: Customer::class, groups: ['admin'])
        )
    )]
    /* #endregion */
    #[Route('/api/customer', name: 'app_create_customer', methods: 'POST')]
    #[IsGranted('ROLE_ADMIN', message: 'You are not allowed to access this resource')]
    public function createCustomer(Request $request, UserPasswordHasherInterface $encoder): JsonResponse
    {
        /*----------------------------------
        | We create the customer
        -----------------------------------*/
        $customer = $this->serializerInterface->deserialize($request->getContent(), Customer::class, 'json');

        /*----------------------------------
        | We check if this customer already exists
        -----------------------------------*/
        $checkForDuplicate = $this->checkForDuplicate->checkForExistingEntry(Customer::class, 'email', $customer->getEmail());
        if ($checkForDuplicate) {
            return $checkForDuplicate;
        }

        /*----------------------------------
        | We manage the password
        -----------------------------------*/
        $password = $customer->getPassword();
        $customer->setPassword($encoder->hashPassword($customer, $password));

        /*----------------------------------
        | We manage the Users Collection
        -----------------------------------*/
        // We empty the customers users collection
        $customer->getUsers()->clear();
        // We get the arrays from the request
        $context = $request->toArray();
        // We check if there are Users affected to this new Customer
        $users = isset($context['users']) ? $context['users'] : null;
        // We loop the array to get the User based on his id and add it to the Customer's Users collection.
        if ($users != null) {
            foreach ($users as $user) {
                $user = $this->em->getRepository(User::class)->findBy(['id' => $user]);
                $customer->addUser($user[0]);
            }
        }

        /*----------------------------------
        | We check if there are any errors
        -----------------------------------*/
        $this->validator->getErrors($customer);

        /*----------------------------------
        | We persist the customer
        -----------------------------------*/
        $this->em->persist($customer);
        $this->em->flush();

        /*----------------------------------
        | We prepare the response
        -----------------------------------*/
        $context = SerializationContext::create()->setGroups(['admin']);
        $jsonCustomer = $this->jmsSerializer->serialize($customer, 'json', $context);

        $location = $this->router->generate('app_detail_customer', ['id' => $customer->getId()]);

        return new JsonResponse($jsonCustomer, Response::HTTP_CREATED, ['Location' => $location], true);
    }
    /* #endregion */

    /* #region PUT a Customer */
    /**
     * PUT method to update a customer
     */
    /* #region Doc */
    #[OA\Response(
        response: 200,
        description: 'Update a customer',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: ['customer:edit']))
        )
    )]
    #[OA\RequestBody(
        description: 'Customer object that needs to be updated to the store',
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: Customer::class, groups: ['customer:edit'])
        )
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'The customer id',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    /* #endregion */
    #[Route('/api/customer/{id}', name: 'app_update_customer', methods: 'PUT')]
    #[IsGranted('ROLE_ADMIN', message: 'You are not allowed to access this resource')]
    public function updateCustomer(Customer $customer, Request $request): JsonResponse
    {
        $newCustomer = $this->serializerInterface->deserialize($request->getContent(), Customer::class, 'json');

        // We go through the new customer's properties and check if there is something to update
        $this->updateEntity->update($customer, $newCustomer, $request);

        // We check if there are errors
        $this->validator->getErrors($customer);

        // We persist the updated informations
        $this->em->persist($customer);
        $this->em->flush();

        // We prepare the Response
        $context = SerializationContext::create()->setGroups(['admin']);
        $jsonCustomer = $this->jmsSerializer->serialize($customer, 'json', $context);

        $location = $this->router->generate('app_detail_customer', ['id' => $customer->getId()]);

        return new JsonResponse($jsonCustomer, Response::HTTP_OK, ['Location' => $location], true);

        // We clear the cache
        $this->tagCache->invalidateTags(['customerCache']);
    }
    /* #endregion */

    /* #region DELETE a Customer */
    /**
     * DELETE method to delete a customer
     */
    /* #region  */
    #[OA\Response(
        response: 200,
        description: 'Delete a customer',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: ['admin']))
        )
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'The customer id',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]

    /* #endregion */    #[Route('/api/customer/{id}', name: 'app_delete_customer', methods: 'DELETE')]
    #[IsGranted('ROLE_ADMIN', message: 'You are not allowed to access this resource')]
    public function deleteCustomer(Customer $customer): JsonResponse
    {
        $customerId = $customer->getId();
        $this->tagCache->invalidateTags(['customerCache']);
        $this->em->remove($customer);
        $this->em->flush();

        // Return new JsonResponse(null, Response::HTTP_NO_CONTENT)
        return new JsonResponse(['status' => 'Customer ' . $customerId . ' deleted'], Response::HTTP_OK);
    }
    /* #endregion */
}
