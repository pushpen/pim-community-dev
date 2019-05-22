<?php

declare(strict_types=1);

namespace Akeneo\UserManagement\Bundle\Command;

use Akeneo\UserManagement\Component\Model\User;
use Akeneo\UserManagement\Component\Model\UserInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Interactive command to create a PIM user.
 *
 * @author    Yohan Blain <yohan.blain@akeneo.com>
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class CreateUserCommand extends ContainerAwareCommand
{
    public const COMMAND_NAME = 'pim:user:create';

    /** @var string */
    private $password;

    /** @var string */
    private $username;

    /** @var string */
    private $firstName;

    /** @var string */
    private $lastName;

    /** @var string */
    private $email;

    /** @var string */
    private $userDefaultLocaleCode;

    /** @var string */
    private $catalogDefaultLocaleCode;

    /** @var string */
    private $catalogDefaultScopeCode;

    /** @var string */
    private $defaultTreeCode;

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName(static::COMMAND_NAME)
            ->setDescription('Creates a PIM user. This command can be launched interactively or non interactively (with the "-n" option). When launched non interactively you have to provide arguments to the command. When launched interactively, command arguments will be ignored.')
            ->addArgument('username')
            ->addArgument('password')
            ->addArgument('email')
            ->addArgument('firstName')
            ->addArgument('lastName')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        if (!$input->isInteractive()) {
            $this->gatherArgumentsForNonInteractiveMode($input);
        }

        $user = $this->getContainer()->get('pim_user.factory.user')->create();
        $this->getContainer()->get('pim_user.updater.user')->update(
            $user,
            [
                'username' => $this->username,
                'password' => $this->password,
                'first_name' => $this->firstName,
                'last_name' => $this->lastName,
                'email' => $this->email,
                'user_default_locale' => $this->userDefaultLocaleCode,
                'catalog_default_locale' => $this->catalogDefaultLocaleCode,
                'catalog_default_scope' => $this->catalogDefaultScopeCode,
                'default_category_tree' => $this->defaultTreeCode,
            ]
        );

        $errors = $this->getContainer()->get('validator')->validate($user);
        if (0 < count($errors)) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }

            throw new \InvalidArgumentException("The user creation failed :\n" . implode("\n", $errorMessages));
        }

        $this->addDefaultGroupTo($user);
        $this->addDefaultRoleTo($user);

        $this->getContainer()->get('pim_user.saver.user')->save($user);

        $output->writeln(sprintf("<info>User %s has been created.</info>", $this->username));
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Please enter the user's information below.");

        $this->username = $this->askForUsername($input, $output);
        $this->password = $this->askForPassword($input, $output);
        $this->confirmPassword($input, $output, $this->password);
        $this->firstName = $this->askForFirstName($input, $output);
        $this->lastName = $this->askForLastName($input, $output);
        $this->email = $this->askForEmail($input, $output);
        $this->userDefaultLocaleCode = $this->askForUserDefaultLocaleCode($input, $output);
        $this->catalogDefaultLocaleCode = $this->askForCatalogDefaultLocaleCode($input, $output);
        $this->catalogDefaultScopeCode = $this->askForCatalogDefaultScopeCode($input, $output);
        $this->defaultTreeCode = $this->askForDefaultTreeCode($input, $output);
    }

    private function askForUsername(InputInterface $input, OutputInterface $output): string
    {
        $question = new Question('Username : ');
        $question->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \InvalidArgumentException("The username is mandatory.");
            }

            return $answer;
        });

        return $this->getHelper('question')->ask($input, $output, $question);
    }

    private function askForPassword(InputInterface $input, OutputInterface $output): string
    {
        $question = new Question('Password (the input will be hidden) : ');
        $question
            ->setHidden(true)
            ->setHiddenFallback(false)
            ->setValidator(function ($answer) {
                if (empty($answer)) {
                    throw new \InvalidArgumentException("The password is mandatory.");
                }

                return $answer;
            });

        return $this->getHelper('question')->ask($input, $output, $question);
    }

    private function confirmPassword(InputInterface $input, OutputInterface $output, string $password): void
    {
        $question = new Question('Confirm password : ');
        $question
            ->setHidden(true)
            ->setHiddenFallback(false)
            ->setValidator(function ($answer) use ($password) {
                if ($password !== $answer) {
                    throw new \InvalidArgumentException("The passwords must match.");
                }

                return $answer;
            });

        $this->getHelper('question')->ask($input, $output, $question);
    }

    private function askForFirstName(InputInterface $input, OutputInterface $output): string
    {
        $question = new Question('First name : ');
        $question->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \InvalidArgumentException("The first name is mandatory.");
            }

            return $answer;
        });

        return $this->getHelper('question')->ask($input, $output, $question);
    }

    private function askForLastName(InputInterface $input, OutputInterface $output): string
    {
        $question = new Question('Last name : ');
        $question->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \InvalidArgumentException("The last name is mandatory.");
            }

            return $answer;
        });

        return $this->getHelper('question')->ask($input, $output, $question);
    }

    private function askForEmail(InputInterface $input, OutputInterface $output): string
    {
        $question = new Question('Email : ');
        $question->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \InvalidArgumentException("The email is mandatory.");
            }

            if (false === filter_var($answer, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException("Please enter a valid email address.");
            }

            return $answer;
        });

        return $this->getHelper('question')->ask($input, $output, $question);
    }

    private function askForUserDefaultLocaleCode(InputInterface $input, OutputInterface $output): string
    {
        $question = new Question('UI default locale code (e.g. "en_US") : ');
        $question->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \InvalidArgumentException("The UI default locale is mandatory.");
            }

            return $answer;
        });

        return $this->getHelper('question')->ask($input, $output, $question);
    }

    private function askForCatalogDefaultLocaleCode(InputInterface $input, OutputInterface $output): string
    {
        $question = new Question('Catalog default locale code (e.g. "en_US") : ');
        $question->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \InvalidArgumentException("The catalog default locale is mandatory.");
            }

            return $answer;
        });

        return $this->getHelper('question')->ask($input, $output, $question);
    }

    private function askForCatalogDefaultScopeCode(InputInterface $input, OutputInterface $output): string
    {
        $question = new Question('Catalog default scope code (e.g. "ecommerce") : ');
        $question->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \InvalidArgumentException("The catalog default scope is mandatory.");
            }

            return $answer;
        });

        return $this->getHelper('question')->ask($input, $output, $question);
    }

    private function askForDefaultTreeCode(InputInterface $input, OutputInterface $output): string
    {
        $question = new Question('Default tree code (e.g. "master") : ');
        $question->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \InvalidArgumentException("The default tree is mandatory.");
            }

            return $answer;
        });

        return $this->getHelper('question')->ask($input, $output, $question);
    }

    /**
     * Adds the default group "All" to the new user.
     */
    private function addDefaultGroupTo(UserInterface $user): void
    {
        $group = $this->getContainer()->get('pim_user.repository.group')->findOneByIdentifier(User::GROUP_DEFAULT);

        if (null === $group) {
            throw new \RuntimeException('Default user group not found.');
        }

        $user->addGroup($group);
    }

    /**
     * Adds the default role "ROLE_USER" to the new user.
     */
    private function addDefaultRoleTo(UserInterface $user): void
    {
        $role = $this->getContainer()->get('pim_user.repository.role')->findOneByIdentifier(User::ROLE_DEFAULT);

        if (null === $role) {
            throw new \RuntimeException('Default user role not found.');
        }

        $user->addRole($role);
    }

    private function gatherArgumentsForNonInteractiveMode(InputInterface $input): void
    {
        if (null !== $input->getArgument('username')) {
            $this->username = $input->getArgument('username');
        }
        if (null !== $input->getArgument('password')) {
            $this->password = $input->getArgument('password');
        }
        if (null !== $input->getArgument('email')) {
            $this->email = $input->getArgument('email');
        }
        if (null !== $input->getArgument('firstName')) {
            $this->firstName = $input->getArgument('firstName');
        }
        if (null !== $input->getArgument('lastName')) {
            $this->lastName = $input->getArgument('lastName');
        }
    }
}
