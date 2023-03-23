<?php

// php bin/console user:helper remove_user_by_username testnibrod
// php bin/console user:helper add_admin_user davor "57744292" ROLE_ADMIN davor@shipshape-solutions.com Davor Spanic "1,10"
// php bin/console user:helper recreate_superadmin_privileges
// php bin/console user:helper set_default_admins
// php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 7 6291ff34a7f0f9.65329474
// php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 6 629200cba71cb9.96857518
// php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 1 fba4d20f6ce62a8135eeac816b22ff9c
// php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 2 fba4d20f6ce62a8135eeac816b22ff9c
// php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 3 fba4d20f6ce62a8135eeac816b22ff9c
// php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 4 fba4d20f6ce62a8135eeac816b22ff9c
// php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 5 fba4d20f6ce62a8135eeac816b22ff9c
// php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 6 fba4d20f6ce62a8135eeac816b22ff9c
// php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 7 62488a5d4246d8.44413530
// php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 7 61f941974d2034.83352780
// php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 5 1b3329290bffea8cc4b871d66d173a67
// php bin/console user:helper add_privileges_to_entity_type_and_group ROLE_COMMERCE_ADMIN s_route_not_found

namespace AppBundle\Command;

use AppBundle\Context\AttributeContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\AdministrationManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\MailManager;
use AppBundle\Managers\PrivilegeManager;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\EmailTemplateManager;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class UserCommand extends ContainerAwareCommand
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var AdministrationManager $administrationManager */
    protected $administrationManager;
    /** @var MailManager $mailManager */
    protected $mailManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;

    protected function configure()
    {
        $this->setName('user:helper')
            ->SetDescription(' description of what the command ')
            ->AddArgument('type', InputArgument :: OPTIONAL, ' which function ')
            ->AddArgument('arg1', InputArgument :: OPTIONAL, ' which arg1 ')
            ->AddArgument('arg2', InputArgument :: OPTIONAL, ' which arg2 ')
            ->AddArgument('arg3', InputArgument :: OPTIONAL, ' which arg3 ')
            ->AddArgument('arg4', InputArgument :: OPTIONAL, ' which arg4 ')
            ->AddArgument('arg5', InputArgument :: OPTIONAL, ' which arg5 ')
            ->AddArgument('arg6', InputArgument :: OPTIONAL, ' which arg6 ')
            ->AddArgument('arg7', InputArgument :: OPTIONAL, ' which arg7 ');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**@var Logger $logger */
        $logger = $this->getContainer()->get('logger');

        /** @var HelperManager $helperManager */
        $helperManager = $this->getContainer()->get("helper_manager");

        $request = new Request();
        $helperManager->loginAnonymus($request, "system");

        /**
         * Check which function
         */
        $func = $input->getArgument('type');
        if (empty($func)) {
            throw new \Exception('Function not defined');
        }

        $arg1 = $input->getArgument('arg1');
        $arg2 = $input->getArgument('arg2');
        $arg3 = $input->getArgument('arg3');
        $arg4 = $input->getArgument('arg4');
        $arg5 = $input->getArgument('arg5');
        $arg6 = $input->getArgument('arg6');
        $arg7 = $input->getArgument('arg7');

        if ($func == "remove_user_by_username") {

            if($_ENV["FRONTEND_BUNDLE"] == "ShapecrmBusinessBundle"){
                return true;
            }

            if(empty($arg1)){
                throw new \Exception("Empty username");
            }

            $user = $helperManager->getUserByUsername($arg1);

            if(empty($user)){
                return true;
            }

            /** @var AdministrationManager $administrationManager */
            $administrationManager = $this->getContainer()->get("administration_manager");

            $administrationManager->deleteUser($user);

        }
        elseif ($func == "add_privileges_to_object_and_group") {

            if(empty($arg1)){
                throw new \Exception("Empty role code");
            }

            if(empty($arg2)){
                throw new \Exception("Empty action type");
            }

            if(empty($arg3)){
                throw new \Exception("Empty action code");
            }

            if(empty($this->databaseContext)){
                $this->databaseContext = $this->getContainer()->get("database_context");
            }

            $q = "SELECT id as count FROM role_entity WHERE role_code = '{$arg1}';";
            $roleId = $this->databaseContext->getSingleResult($q);

            if(empty($roleId)){
                throw new \Exception("Missing role");
            }

            $q = "INSERT IGNORE INTO privilege (role,action_type,action_code) VALUES ({$roleId},{$arg2},'{$arg3}');";
            $this->databaseContext->executeNonQuery($q);

            return true;
        }
        elseif ($func == "add_privileges_to_entity_type_and_group") {

            if(empty($arg1)){
                throw new \Exception("Empty role code");
            }

            if(empty($arg2)){
                throw new \Exception("Empty entity type");
            }

            if(empty($this->databaseContext)){
                $this->databaseContext = $this->getContainer()->get("database_context");
            }

            $q = "SELECT id as count FROM role_entity WHERE role_code = '{$arg1}';";
            $roleId = $this->databaseContext->getSingleResult($q);

            if(empty($roleId)){
                throw new \Exception("Missing role");
            }

            $q = "SELECT id,uid FROM entity_type WHERE entity_type_code = 's_route_not_found';";
            $entityType = $this->databaseContext->getSingleEntity($q);

            $q = "SELECT id,uid FROM attribute_set WHERE entity_type_id = {$entityType["id"]};";
            $attributeSet = $this->databaseContext->getSingleEntity($q);

            $q = "INSERT IGNORE INTO privilege (role,action_type,action_code) VALUES ({$roleId},1,'{$attributeSet["uid"]}');";
            $this->databaseContext->executeNonQuery($q);
            $q = "INSERT IGNORE INTO privilege (role,action_type,action_code) VALUES ({$roleId},2,'{$attributeSet["uid"]}');";
            $this->databaseContext->executeNonQuery($q);
            $q = "INSERT IGNORE INTO privilege (role,action_type,action_code) VALUES ({$roleId},3,'{$attributeSet["uid"]}');";
            $this->databaseContext->executeNonQuery($q);
            $q = "INSERT IGNORE INTO privilege (role,action_type,action_code) VALUES ({$roleId},4,'{$attributeSet["uid"]}');";
            $this->databaseContext->executeNonQuery($q);

            $q = "SELECT id,uid FROM page WHERE entity_type = {$entityType["id"]};";
            $pages = $this->databaseContext->getAll($q);

            if(!empty($pages)){
                foreach ($pages as $page){
                    $q = "INSERT IGNORE INTO privilege (role,action_type,action_code) VALUES ({$roleId},5,'{$page["uid"]}');";
                    $this->databaseContext->executeNonQuery($q);
                }
            }

            $q = "SELECT id,uid FROM list_view WHERE entity_type = {$entityType["id"]};";
            $listViews = $this->databaseContext->getAll($q);

            if(!empty($listViews)){
                foreach ($listViews as $listView){
                    $q = "INSERT IGNORE INTO privilege (role,action_type,action_code) VALUES ({$roleId},6,'{$listView["uid"]}');";
                    $this->databaseContext->executeNonQuery($q);
                }
            }

            $q = "SELECT id,uid FROM page_block WHERE entity_type = {$entityType["id"]};";
            $pageBlocks = $this->databaseContext->getAll($q);

            if(!empty($pageBlocks)){
                foreach ($pageBlocks as $pageBlock){
                    $q = "INSERT IGNORE INTO privilege (role,action_type,action_code) VALUES ({$roleId},7,'{$pageBlock["uid"]}');";
                    $this->databaseContext->executeNonQuery($q);
                }
            }

            return true;
        }
        elseif ($func == "add_admin_user") {

            if(empty($arg1)){
                throw new \Exception("Empty username");
            }
            if(empty($arg2)){
                throw new \Exception("Empty password");
            }
            if(empty($arg3)){
                throw new \Exception("Empty role");
            }
            if(empty($arg4)){
                throw new \Exception("Empty email");
            }
            if(empty($arg5)){
                throw new \Exception("Empty first name");
            }
            if(empty($arg6)){
                throw new \Exception("Empty last name");
            }
            if(empty($arg7)){
                throw new \Exception("Empty role ids");
            }

            $user = $helperManager->getUserByUsername($arg1);
            if(!empty($user)){
                return true;
            }

            if(empty($this->administrationManager)){
                $this->administrationManager = $this->getContainer()->get("administration_manager");
            }

            $userToDelete = $helperManager->getUserByEmail($arg4);
            if(!empty($userToDelete)){

                if(empty($this->accountManager)){
                    $this->accountManager = $this->getContainer()->get("account_manager");
                }

                if(empty($this->entityManager)){
                    $this->entityManager = $this->getContainer()->get("entity_manager");
                }

                /** @var AccountEntity $account */
                $account = $this->accountManager->getAccountByFilter("email",$arg4);
                if(!empty($contact)){
                    $this->entityManager->deleteEntityFromDatabase($account);
                }

                /** @var ContactEntity $contact */
                $contact = $this->accountManager->getContactByEmail($arg4);
                if(!empty($contact)){
                    $this->entityManager->deleteEntityFromDatabase($contact);
                }

                $this->administrationManager->deleteUser($userToDelete);
            }

            /** @var AttributeContext $attributeContext */
            $attributeContext = $this->getContainer()->get("attribute_context");

            /** @var Attribute $attribute */
            $attribute = $attributeContext->getItemByUid("609fcb8323db52.52811666");

            $p = Array();
            $p["username"] = $arg1;
            $p["password"] = $arg2;
            $p["password_again"] = $arg2;
            $p["enabled"] = true;
            $p["expired"] = false;
            $p["credentials_expired"] = false;
            $p["roles"] = serialize(array($arg3));
            $p["locked"] = null;
            $p["username_canonical"] = $arg1;
            $p["email"] = $arg4;
            $p["email_canonical"] = $arg4;
            $p["system_role"] = $arg3;
            $p["salt"] = StringHelper::generateRandomString(31);
            $p["color"] = "#ff0000";
            $p["core_language_id"] = "1";
            $p["first_name"] = $arg5;
            $p["last_name"] = $arg6;
            $p["multiselect"][] = Array(
                "parent_entity" => "core_user",
                "child_entity" => "role",
                "link_entity" => "core_user_role_link",
                "attribute_id" => $attribute->getId(),
                "related_ids" => explode(",",$arg7)
            );

            $type = "core_user";

            $factoryManager = $this->getContainer()->get('factory_manager');
            $formManager = $factoryManager->loadFormManager($type);

            /** @var CoreUserEntity $entity */
            $entity = $formManager->saveFormModel($type, $p);
            if (!$entity) {
                throw new \Exception("User {$arg4} not created");
            }
            if(empty($this->entityManager)){
                $this->entityManager = $this->getContainer()->get("entity_manager");
            }
            $this->entityManager->refreshEntity($entity);

            $user = $helperManager->getUserBySalt($p["salt"]);



            /**
             * Set password
             */
            $user = $this->administrationManager->setUserPassword($user, $p["password"]);
            if ($p["system_role"] == "ROLE_ADMIN") {
                $user->setEntityStateId(2);
            }

            $user = $this->administrationManager->saveUser($user);
            if (!$user) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            /**
             * Check if is admin
             */
            $isAdmin = false;
            if(isset($_ENV["FRONTEND_ADMIN_ACCOUNT_ROLES"]) && !empty($_ENV["FRONTEND_ADMIN_ACCOUNT_ROLES"])){
                $frontendAdminAccountRoles = json_decode($_ENV["FRONTEND_ADMIN_ACCOUNT_ROLES"], true);
                $roleCodes = $entity->getUserRoleCodes();
                if (EntityHelper::isCountable($roleCodes) && count($roleCodes) > 0 && count(array_intersect($frontendAdminAccountRoles, $roleCodes)) != 0) {
                    $isAdmin = true;
                }
            }

            /**
             * Automaticly create frontend account if needed
             */
            if(isset($_ENV["FRONTEND_ADMIN_ACCOUNT_CREATE"]) && $_ENV["FRONTEND_ADMIN_ACCOUNT_CREATE"] && $isAdmin){
                $helperManager->generateAccountAndContactForAdmin($entity);
            }

            /**
             * Send new account email
             */
            if($isAdmin){

                if(empty($this->mailManager)){
                    $this->mailManager = $this->getContainer()->get("mail_manager");
                }

                /** @var EmailTemplateManager $emailTemplateManager */
                $emailTemplateManager = $this->getContainer()->get('email_template_manager');
                /** @var EmailTemplateEntity $template */
                $template = $emailTemplateManager->getEmailTemplateByCode("new_admin_account");
                if (!empty($template)) {

                    if(empty($this->helperManager)){
                        $this->helperManager = $this->getContainer()->get("helper_manager");
                    }

                    /** @var CoreUserEntity $coreUser */
                    $coreUser = $this->helperManager->getCoreUserById($entity->getId());

                    $templateData = $emailTemplateManager->renderEmailTemplate($coreUser, $template);

                    $templateAttachments = $template->getAttachments();
                    if (!empty($templateAttachments)) {
                        $attachments = $template->getPreparedAttachments();
                    }

                    $this->mailManager->sendEmail(array('email' => $coreUser->getEmail(), 'name' => $coreUser->getFullName()), null, null, null, $templateData["subject"], "", null, [], $templateData["content"], $attachments ?? [], $_ENV["DEFAULT_STORE_ID"]);
                } else {
                    $this->mailManager->sendEmail(array('email' => $entity->getEmail(), 'name' => $entity->getEmail()), null, null, null, "New admin account", "", "new_admin_account", array("user" => $entity, "password" => $p["password"]));
                }
            }
            else{
                //TODO send frontend account
            }

            return true;
        }
        elseif ($func == "recreate_superadmin_privileges") {

            /** @var PrivilegeManager $privilegeManager */
            $privilegeManager = $this->getContainer()->get("privilege_manager");

            $privilegeManager->recreateRolePrivileges(1,'attribute_set',1);
            $privilegeManager->recreateRolePrivileges(1,'attribute_set',2);
            $privilegeManager->recreateRolePrivileges(1,'attribute_set',3);
            $privilegeManager->recreateRolePrivileges(1,'attribute_set',4);
            $privilegeManager->recreateRolePrivileges(1,'page',5);
            $privilegeManager->recreateRolePrivileges(1,'list_view',6);
            $privilegeManager->recreateRolePrivileges(1,'page_block',7);

            return true;
        }
        elseif ($func == "set_default_admins"){

            $command = $this->getApplication()->find('user:helper');

            $arguments = [
                'type'    => 'remove_user_by_username',
                'arg1'    => 'testnibrod'
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            $arguments = [
                'type'    => 'remove_user_by_username',
                'arg1'    => 'ksusac'
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            $arguments = [
                'type'    => 'remove_user_by_username',
                'arg1'    => 'partner'
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            $arguments = [
                'type'    => 'remove_user_by_username',
                'arg1'    => 'petar'
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            #IGOR
            $arguments = [
                'type'    => 'add_admin_user',
                'arg1'    => 'igor',
                'arg2'    => '0918843391',
                'arg3'    => 'ROLE_ADMIN',
                'arg4'    => 'igor@shipshape-solutions.com',
                'arg5'    => 'Igor',
                'arg6'    => 'Draušnik',
                'arg7'    => '1,10'
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            #DEA
            $arguments = [
                'type'    => 'add_admin_user',
                'arg1'    => 'dea',
                'arg2'    => '22022022',
                'arg3'    => 'ROLE_ADMIN',
                'arg4'    => 'dea@shipshape-solutions.com',
                'arg5'    => 'Dea',
                'arg6'    => 'Marušić',
                'arg7'    => '1,10'
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            #DAVOR
            $arguments = [
                'type'    => 'add_admin_user',
                'arg1'    => 'davor',
                'arg2'    => '57744292',
                'arg3'    => 'ROLE_ADMIN',
                'arg4'    => 'davor@shipshape-solutions.com',
                'arg5'    => 'Davor',
                'arg6'    => 'Španić',
                'arg7'    => '1,10'
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            #ALEN
            $arguments = [
                'type'    => 'add_admin_user',
                'arg1'    => 'alen',
                'arg2'    => '57744292',
                'arg3'    => 'ROLE_ADMIN',
                'arg4'    => 'alen.pagac@gmail.com',
                'arg5'    => 'Alen',
                'arg6'    => 'Pagač',
                'arg7'    => '1,10'
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            #HRVOJE
            $arguments = [
                'type'    => 'add_admin_user',
                'arg1'    => 'hrvoje',
                'arg2'    => '0995117889$hrc',
                'arg3'    => 'ROLE_ADMIN',
                'arg4'    => 'hrvoje.rukavina@shipshape-solutions.com',
                'arg5'    => 'Hrvoje',
                'arg6'    => 'Rukavina',
                'arg7'    => '1,10'
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            #VALENTINO
            $arguments = [
                'type'    => 'add_admin_user',
                'arg1'    => 'valentino',
                'arg2'    => 'Excel_22',
                'arg3'    => 'ROLE_ADMIN',
                'arg4'    => 'valentino@shipshape-solutions.com',
                'arg5'    => 'Valentino',
                'arg6'    => 'Mrazović',
                'arg7'    => '1,10'
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            #ERNEST
            $arguments = [
                'type'    => 'add_admin_user',
                'arg1'    => 'ernest',
                'arg2'    => '12111211',
                'arg3'    => 'ROLE_ADMIN',
                'arg4'    => 'ernest@shipshape-solutions.com',
                'arg5'    => 'Ernest',
                'arg6'    => 'Antolović',
                'arg7'    => '1,10'
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            #IVAN
            $arguments = [
                'type'    => 'add_admin_user',
                'arg1'    => 'ivan',
                'arg2'    => 'Zelen54!',
                'arg3'    => 'ROLE_ADMIN',
                'arg4'    => 'ivan@shipshape-solutions.com',
                'arg5'    => 'Ivan',
                'arg6'    => 'Vidović',
                'arg7'    => '1,10'
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            #TOMISLAV
            $arguments = [
                'type'    => 'add_admin_user',
                'arg1'    => 'tomislav',
                'arg2'    => '57744292',
                'arg3'    => 'ROLE_ADMIN',
                'arg4'    => 'tomislav@shipshape-solutions.com',
                'arg5'    => 'Tomislav',
                'arg6'    => 'Burić',
                'arg7'    => '1,10'
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            #VIKI
            $arguments = [
                'type'    => 'add_admin_user',
                'arg1'    => 'vjurkovic',
                'arg2'    => '0917943609',
                'arg3'    => 'ROLE_ADMIN',
                'arg4'    => 'viktorija@shipshape-solutions.com',
                'arg5'    => 'Viktorija',
                'arg6'    => 'Jurković',
                'arg7'    => '1,10'
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            #RECREATE USER PRIVILEGES
            $arguments = [
                'type'    => 'recreate_superadmin_privileges',
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            $command = $this->getApplication()->find('update:helper');

            $arguments = [
                'type'    => 'generate_account_and_contact_for_all_admin',
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            return true;
        }
        else{
            throw new \Exception("Command type missing: ".json_encode($input->getArguments()));
        }

        return false;
    }
}
