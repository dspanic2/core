<?php

// php bin/console admin:translation update_bundle AppBundle hr
// php bin/console admin:translation get_strings AppBundle
// php bin/console admin:translation translate_bundle AppBundle hr

namespace AppBundle\Command\Deprecated;

use AppBundle\Context\PageBlockContext;
use AppBundle\Context\PageContext;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\Page;
use AppBundle\Entity\PageBlock;
use AppBundle\Managers\DatabaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Definitions\ColumnDefinitions;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\PageManager;
use AppBundle\Managers\TranslationManager;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TranslationCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this->setName('admin:translation')
            ->SetDescription(' description of what the command ')
            ->AddArgument('type', InputArgument :: OPTIONAL, ' which function ')
            ->AddArgument('bundle', InputArgument :: OPTIONAL, ' which bundle ')
            ->AddArgument('lang', InputArgument :: OPTIONAL, ' which language ');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**@var Logger $logger */
        $logger = $this->getContainer()->get('logger');

        /** @var TranslationManager $translationManager */
        $translationManager = $this->getContainer()->get("translation_manager");

        /**
         * Check which function
         */
        $func = $input->getArgument('type');
        if (empty($func)) {
            throw new \Exception('Function not defined');
        }

        $bundle = $input->getArgument('bundle');
        if (empty($bundle)) {
            throw new \Exception('Bundle not defined');
        }

        $bundleFound = false;

        $bundles = $this->getContainer()->getParameter('kernel.bundles');
        foreach ($bundles as $key => $value) {
            if ($key == $bundle) {
                $bundleFound = true;
                break;
            }
        }

        if (!$bundleFound) {
            throw new \Exception('Bundle does not exist');
        }

        if ($func == "get_strings") {
            $translationManager->getStrings($bundle);
        } elseif ($func == "update_bundle") {
            $langCode = $input->getArgument('lang');
            if (empty($langCode)) {
                throw new \Exception('Lang code not defined');
            }

            if (strlen(trim($langCode)) != 2) {
                throw new \Exception('Lang code is not correct');
            }

            $langCode = strtolower($langCode);

            if (empty($translationManager->checkIfLangCodeExists($langCode))) {
                throw new \Exception('Lang code does not exist in database');
            }

            $translationManager->createTranslationForBundle($bundle, $langCode);
        } elseif ($func == "translate_bundle") {
            $langCode = $input->getArgument('lang');
            if (empty($langCode)) {
                throw new \Exception('Lang code not defined');
            }

            if (strlen(trim($langCode)) != 2) {
                throw new \Exception('Lang code is not correct');
            }

            $langCode = strtolower($langCode);

            if (empty($translationManager->checkIfLangCodeExists($langCode))) {
                throw new \Exception('Lang code does not exist in database');
            }

            $translationManager->translateBundle($bundle, $langCode);
        }
        else{
            throw new \Exception("Command type missing: ".json_encode($input->getArguments()));
        }

        return false;
    }
}
