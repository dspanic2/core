<?php

namespace AppBundle\Command;

// php bin/console cron:run scheduled
// php bin/console cron:run command crmhelper:run sync_exchange_rates
// php bin/console cron:run check_for_missed_cron_job
// php bin/console cron:run add_update_cron_job 'Unlock quotes' '* * * * *' 'Unlock quotes' 'crmhelper:run type:unlock_quotes' 0 2
// php bin/console cron:run add_update_cron_job 'Automatically fix routes' '*/55 * * * *' 'Automatically fix routes' 'scommercehelper:function type:automatically_fix_rutes' 0 10

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CronJobEntity;
use AppBundle\Entity\CronJobHistoryEntity;
use AppBundle\Entity\SearchFilter;
use AppBundle\Managers\CronJobManager;
use AppBundle\Managers\ErrorLogManager;
use AppBundle\Managers\HelperManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class CronJobCommand extends ContainerAwareCommand
{
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;

    protected function configure()
    {
        $this->setName('cron:run')
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

        if ($func == "scheduled") {

            /** @var CronJobManager $cronJobManager */
            $cronJobManager = $this->getContainer()->get("cron_job_manager");

            $ret = $cronJobManager->getScheduledCronJobs($arg1);


            $output->writeln(implode("||", $ret));
        } elseif ($func == "command") {

            $command = $input->getArgument('arg1');

            if (empty($command)) {
                return false;
            }

            /** @var CronJobManager $cronJobManager */
            $cronJobManager = $this->getContainer()->get("cron_job_manager");

            /** @var CronJobEntity $cronJob */
            $cronJob = $cronJobManager->getCronJobByMethod($command);
            if (empty($cronJob)) {
                return false;
            }

            $params = explode(" ", $command);
            $method = $params[0];
            unset($params[0]);

            /** @var Command $command */
            $command = $this->getApplication()->find($method);

            $arguments = array();
            if (!empty($params)) {
                foreach ($params as $key => $param) {
                    $param = explode(":", $param);
                    if (isset($param[1])) {
                        $paramKey = $param[0];
                        $paramValue = $param[1];
                    } else {
                        if ($key == 1) {
                            $paramKey = "type";
                            $paramValue = $param[0];
                        } elseif (stripos($param[0],"--") !== false){
                            $k = $key - 1;
                            $paramKey = explode("=",$param[0])[0];
                            $paramValue = explode("=",$param[0])[1];
                        } else {
                            $k = $key - 1;
                            $paramKey = "arg{$k}";
                            $paramValue = $param[0];
                        }
                    }
                    $arguments[$paramKey] = $paramValue;
                }
            }

            $greetInput = new ArrayInput($arguments);

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("cronJob", "eq", $cronJob->getId()));
            $compositeFilter->addFilter(new SearchFilter("dateStarted", "nu", null));

            /** @var CronJobHistoryEntity $cronJobHistory */
            $cronJobHistory = $cronJobManager->getCronJobHistoryByFilter($compositeFilter);

            if(empty($cronJobHistory)){
                return false;
            }

            $data = array();

            $data["date_started"] = new \DateTime();
            $cronJobManager->insertUpdateCronJobHistory($data, $cronJobHistory);

            $data = array();

            try {
                $result = $command->run($greetInput, $output);
                if (!empty($result)) {
                    $data["error_log"] = json_encode($result);
                }
            } catch (\Exception $e) {
                $data["error_log"] = $e->getMessage();
                if (empty($this->errorLogManager)) {
                    $this->errorLogManager = $this->getContainer()->get("error_log_manager");
                }
                $this->errorLogManager->logExceptionEvent("Cron job error",$e,true,null);
            }

            $data["is_running"] = 0;
            $data["date_finished"] = new \DateTime();
            $data["has_error"] = !empty($data["error_log"]);
            $cronJobManager->insertUpdateCronJobHistory($data, $cronJobHistory);
            return true;

        }
        elseif ($func == "add_update_cron_job"){

            $data = Array();

            $data["name"] = $input->getArgument('arg1');
            $data["schedule"] = $input->getArgument('arg2');
            $data["description"] = $input->getArgument('arg3');
            $data["method"] = $input->getArgument('arg4');
            $data["is_active"] = $input->getArgument('arg5');
            $data["run_time"] = $input->getArgument('arg6');

            /** @var CronJobManager $cronJobManager */
            $cronJobManager = $this->getContainer()->get("cron_job_manager");

            $cronJob = $cronJobManager->addUpdateCronJob($data);

            if(empty($cronJob)){
                print_r("Error adding/updateing cron job: ".json_encode($data));
            }

            return false;
        }
        elseif ($func == "check_for_missed_cron_job"){

            /** @var CronJobManager $cronJobManager */
            $cronJobManager = $this->getContainer()->get("cron_job_manager");

            $cronJob = $cronJobManager->getCronJobByName("Wand import");

            $cronJobManager->checkForMissedCronJob($cronJob);
        }
        else{
            throw new \Exception("Command type missing: ".json_encode($input->getArguments()));
        }

        return false;
    }
}
