<?php

// php bin/console helper:function generate_yearly_absence
// php bin/console helper:function generate_next_year_absence
// php bin/console helper:function remove_absence_from_last_year   ----- na 30.6.
// php bin/console helper:function transfer_left_days_from_last_year  ---- na 1.1.

#0 6 30 6 *    php bin/console helper:function remove_absence_from_last_year  >> /dev/null 2>&1
#0 6 1 1 *    php bin/console helper:function transfer_left_days_from_last_year  >> /dev/null 2>&1
// php bin/console helper:function fix_missing_absence_employee_year -------- FIX FOR MISSING EMPLOYEE YEAR ON ABSENCE
// php bin/console helper:function recalculate_all -------- FIX RECALCULATE ALL

namespace HrBusinessBundle\Command;

use AppBundle\Entity\EntityType;
use AppBundle\Managers\DatabaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Definitions\ColumnDefinitions;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use Monolog\Logger;
use HrBusinessBundle\Managers\HrManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class HelperCommand extends ContainerAwareCommand
{
    protected function  configure()
    {
        $this->setName('helper:function')
            ->SetDescription('Helper functions')
            ->AddArgument('type', InputArgument :: OPTIONAL, ' which function ');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**
         * Start new session for import
         */
        $request = new Request();
        if (!empty($request->getSession())) {
            $request->getSession()->invalidate();
        }

        /** @var HelperManager $helperManager */
        $helperManager = $this->getContainer()->get("helper_manager");

        $request = new Request();
        $helperManager->loginAnonymus($request, "system");
        /**
         * End start new session for import
         */

        /**@var Logger $logger */
        $logger = $this->getContainer()->get('logger');

        /**@var HrManager $hrManager */
        $hrManager = $this->getContainer()->get('hr_manager');

        $func = $input->getArgument('type');
        if ($func == "generate_yearly_absence") {

            $year = date("Y",time());
            $hrManager->generateYearlyAbsence($year);
        }
        elseif ($func == "generate_next_year_absence") {

            $year = date("Y",time());
            $year = intval($year) + 1;
            $hrManager->generateYearlyAbsence($year);
        }
        elseif ($func == "remove_absence_from_last_year") {
            $year = date("Y",time());
            $hrManager->removeAbsenceFromLastYear($year);
        }
        /*elseif ($func == "transfer_left_days_from_last_year") {
            $year = date("Y",time());
            $hrManager->transferLeftDaysFromLastYear($year);
        }*/
        elseif ($func == "fix_missing_absence_employee_year") {
            $hrManager->fixMissingAbsenceEmployeeYear();
        }
        elseif ($func == "recalculate_all") {
            $hrManager->recalculateAllAbsences();
            $hrManager->recalculateAll();
        }
        else{
            throw new \Exception("Command type missing: ".json_encode($input->getArguments()));
        }

        return false;
    }
}

