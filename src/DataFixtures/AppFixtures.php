<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Product;

use App\Entity\Customer;
use Doctrine\Persistence\ObjectManager;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $encoder;

    // We set up the Password Hasher
    public function __construct(UserPasswordHasherInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager): void
    {
        /**
         * We use a loop to create 3 new customers, one admin, one user and one guest
         */
        for ($i = 0; $i < 20; $i++) {
            $customer = new Customer();
            $customer->setEmail('customer' . $i . '@example.com')
                ->setRoles(['ROLE_USER'])
                ->setPassword($this->encoder->hashPassword($customer, 'password'));
            switch ($i) {
                case 0:
                    $customer->setRoles(['ROLE_ADMIN'])
                        ->setEmail('admin@example.com');
                    break;
                case 1:
                    $customer->setRoles(['ROLE_USER'])
                        ->setEmail('user@example.com');
                    break;
                case 2:
                    $customer->setRoles(['ROLE_GUEST'])
                        ->setEmail('guest@example.com');
                    break;
                default:
                    // We randomly assign a role USER or GUEST to the customer
                    $customer->setRoles(['ROLE_USER'])
                        ->setEmail('customer' . $i . '@example.com');
                    break;
            }
            $manager->persist($customer);
            $listCustomer[] = $customer;
        }

        /**
         * We use a loop to create 10 new users, randomly affected to a customer
         */
        for ($i = 0; $i < 50; $i++) {
            $user = new User();
            $user->setUsername('user' . $i)
                ->setEmail('user' . $i . '@example.com')
                ->setPassword($this->encoder->hashPassword($user, 'password'))
                ->setRoles(['ROLE_GUEST'])
                ->setCustomer($listCustomer[array_rand($listCustomer)]);
            $manager->persist($user);
            $listUser[] = $user;
        }

        /**
         * We use a loop to generate 100 random products with random prices and descriptions and randomly assigned to a user
         */
        for ($i = 0; $i < 100; $i++) {
            $product = new Product();
            $product->setName('Product ' . $i)
                ->setPrice(rand(1, 1000))
                ->setDescription('Description of product ' . $i)
                ->addOwner($listUser[array_rand($listUser)]);
            $manager->persist($product);
        }

        $manager->flush();
    }
}
