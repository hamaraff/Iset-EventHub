<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User;


class CreateAdminCommand extends Command
{
    protected static $defaultName = 'app:create-admin';
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    
    public function __construct(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    )
    {
        parent::__construct();
        $this->em = $em;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
$this->setDescription('Create or Update Admin User')
        ->addArgument('email',InputArgument::REQUIRED,'User email')
        ->addArgument('name',InputArgument::REQUIRED,'User Name')
        ->addArgument('password',InputArgument::REQUIRED,'User Password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input,$output);
        $io->text('Creating new admin...');

        $email = trim($input->getArgument('email'));
        $name = trim($input->getArgument('name'));
        $password = trim($input->getArgument('password'));
        // validate inputs
        if (empty($email) || empty($name) || empty($password)) {
            $io->error('Email, name and password cannot be empty.');
            return Command::INVALID;
        }
        // get repository
        $userRepo = $this->em->getRepository(User::Class);
        // find user by email
        $user = $userRepo->findOneBy(['email'=>$email]);
        $isNew = false;
        // if user not found, create new user

        if(!$user){
            $io->text('Creating new admin...');
            $user = new User();
            $user->setEmail($email);
            $isNew = true;
        }else{
            $io->text('User Deja Mawjoud -> Updating existing admin...');
        }
        // update user
        $user->setName($name);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        $user->setRoles(['ROLE_ADMIN']);
        $this->em->persist($user);
        $this->em->flush();
        $io->success('Admin updated successfully : '.$email);
        return Command::SUCCESS;
    }
}
