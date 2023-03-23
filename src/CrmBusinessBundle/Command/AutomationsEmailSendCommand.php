<?php

// php bin/console automationsEmail:run remind_me //todo svaki sat
// php bin/console automationsEmail:run low_prod_qty //todo svaki sat
// php bin/console automationsEmail:run generate_discount_coupon_from_template newsletter_application
// php bin/console automationsEmail:run run_marketing_rule watch_x_times
// php bin/console automationsEmail:run run_marketing_rule unfinished_cart
// php bin/console automationsEmail:run run_marketing_results_queue 1
// php bin/console automationsEmail:run run_marketing_results_queue_test 0 54


namespace CrmBusinessBundle\Command;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Managers\AutomationsManager;
use CrmBusinessBundle\Managers\DiscountCouponManager;
use CrmBusinessBundle\Managers\NewsletterManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class AutomationsEmailSendCommand extends ContainerAwareCommand
{
    /** @var AutomationsManager $automationsManager */
    protected $automationsManager;
    /** @var DiscountCouponManager $discountCouponManager */
    protected $discountCouponManager;

    protected function configure()
    {
        $this->setName('automationsEmail:run')
            ->SetDescription('Email functions')
            ->AddArgument('function', InputArgument::OPTIONAL, 'function')
            ->AddArgument('arg1', InputArgument::OPTIONAL, "arg1", null)
            ->AddArgument('arg2', InputArgument::OPTIONAL, "arg2", null);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        /**
         * Start new session for import
         */
        $request = new Request();
        if (!empty($request->getSession())) {
            $request->getSession()->invalidate();
        }

        /** @var HelperManager $helperManager */
        $helperManager = $container->get("helper_manager");

        $request = new Request();
        $helperManager->loginAnonymus($request, "system");
        /**
         * End start new session for import
         */

        $func = $input->getArgument("function");
        if ($func == "remind_me"){

            if(empty($this->automationsManager)){
                $this->automationsManager = $this->getContainer()->get("automations_manager");
            }

            $this->automationsManager->sendRemindMeEmails();

            return true;
        } else if ($func == "low_prod_qty"){
            if(empty($this->automationsManager)){
                $this->automationsManager = $this->getContainer()->get("automations_manager");
            }

            $this->automationsManager->sendWarningEmailsForProductsWithLowQty();

            return true;
        }
        else if ($func == "generate_discount_coupon_from_template"){

            $arg1 = $input->getArgument('arg1');

            if(empty($this->discountCouponManager)){
                $this->discountCouponManager = $this->getContainer()->get("discount_coupon_manager");
            }

            $this->discountCouponManager->generateCouponFromTemplate($arg1);

            return true;
        }
        else if ($func == "run_marketing_results_queue"){

            $arg1 = $input->getArgument('arg1');

            if(empty($this->automationsManager)){
                $this->automationsManager = $this->getContainer()->get("automations_manager");
            }

            // Rendering template pieces without request causes an exception
            // "An exception has been thrown during the rendering of a template ("Rendering a fragment can only be done when handling a Request.")."
            $request = new Request();
            $_SERVER['HTTP_USER_AGENT'] = "";
            $storage = new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage();
            $session = new \Symfony\Component\HttpFoundation\Session\Session($storage);
            $request->setSession($session);
            $this->getContainer()->get('request_stack')->push($request);

            $this->automationsManager->runMarketingResultsQueue($arg1);

            return true;
        }
        else if ($func == "run_marketing_results_queue_test"){

            $arg1 = $input->getArgument('arg1');

            $arg2 = $input->getArgument('arg2');

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("id", "eq", $arg2));

            if(empty($this->automationsManager)){
                $this->automationsManager = $this->getContainer()->get("automations_manager");
            }

            $this->automationsManager->runMarketingResultsQueue($arg1,$compositeFilter);

            return true;
        }
        else if ($func == "run_marketing_rule"){

            $arg1 = $input->getArgument('arg1');
            $arg2 = $input->getArgument('arg2');

            if(empty($this->automationsManager)){
                $this->automationsManager = $this->getContainer()->get("automations_manager");
            }

            $this->automationsManager->runMarketingRuleByCode($arg1,$arg2);

            return true;
        }
        else {
            throw new \Exception("Command type missing: ".json_encode($input->getArguments()));
        }

        return false;
    }
}
