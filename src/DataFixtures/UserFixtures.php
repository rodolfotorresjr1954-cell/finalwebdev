<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;
    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }   
    public function load(ObjectManager $manager): void
    {
        $admin= new User();
        $admin->setUsername('adminss');
        $admin->setRoles (['ROLE_ADMIN']);
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'adminpassword');     
        $admin->setPassword($hashedPassword);
        $manager->persist($admin);


        $staff = new User();
        $staff->setUsername('staffs');
        $staff->setRoles (['ROLE_STAFF']);
        $hashedPassword = $this->passwordHasher->hashPassword($staff,'staffpassword');
        $staff->setPassword($hashedPassword);
        $manager->persist($staff);


        $manager->flush();
    }
}
