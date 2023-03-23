<?php

// php bin/console dpdhelper:run dpd_request_parcel arg1
// php bin/console dpdhelper:run dpd_parcel_status arg1 ?arg2
// php bin/console dpdhelper:run dpd_parcel arg1 arg
// php bin/console dpdhelper:run dpd_parcel_print_label arg1
// php bin/console dpdhelper:run dpd_parcel_status_for_all_unfinished

namespace DPDBusinessBundle\Command;

use AppBundle\Managers\HelperManager;

use DPDBusinessBundle\Entity\DpdParcelEntity;
use DPDBusinessBundle\Entity\DpdParcelNumbersEntity;
use DPDBusinessBundle\Entity\DpdParcelParcelNumberLinkEntity;
use DPDBusinessBundle\Managers\DPDManager;
use IntegrationBusinessBundle\Managers\WandApiManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Console\Question\Question;

class DPDHelperCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName("dpdhelper:run")
            ->AddArgument("type", InputArgument::OPTIONAL, " which function ")
            ->AddArgument('arg1', InputArgument :: OPTIONAL, ' which arg1 ')
            ->AddArgument('arg2', InputArgument :: OPTIONAL, ' which arg2 ');
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

        $arg1 = $input->getArgument('arg1');
        $arg2 = $input->getArgument('arg2');

        /**
         * End start new session for import
         */
        $func = $input->getArgument("type");
        if ($func == "dpd_request_parcel") {

            if (empty($arg1) && !is_int($arg1)) {
                echo 'Please enter id' . "\n";
                return false;
            }

            /** @var DPDManager $manager */
            $manager = $this->getContainer()->get("dpd_manager");

            /** @var DpdParcelEntity $dpdParcel */
            $dpdParcel = $manager->getDPDEntityById($arg1, 'dpd_parcel');

            if ($dpdParcel->getRequested()){
                echo 'Already requested' . "\n";
                return false;
            }

            /**
             * Ovdje se requestaju parcel numberi (njihov broj ovisi o polju number_of_parcels)
             */
            $res = $manager->requestDPDParcel($dpdParcel);

            if ($res['error'] === true){
                echo $res['message'] . "\n";
                return false;
            }

            echo 'Success' . "\n";
            return true;

        }  else if ($func == 'dpd_parcel'){

            if (empty($arg1) && !is_string($arg1)) {
                echo 'Please enter operation' . "\n";
                return false;
            }

            if (empty($arg2) && !is_int($arg2)) {
                echo 'Please enter id' . "\n";
                return false;
            }

            /** @var DPDManager $manager */
            $manager = $this->getContainer()->get("dpd_manager");

            /** @var DpdParcelEntity $dpdParcel */
            $dpdParcel = $manager->getDPDEntityById($arg2, 'dpd_parcel');

            $res = $manager->deleteOrCancelDPDParcel($dpdParcel, $arg1);

            if ($res['error'] === true){
                echo $res['message'] . "\n";
                return false;
            }

            echo "Success\n";
            return true;
        } else if ($func == 'dpd_parcel_print_label'){

            if (empty($arg1) && !is_int($arg1)) {
                echo 'Please enter id' . "\n";
                return false;
            }

            $data = [];
            if (!empty($arg2)){
                $data = explode(',', $arg2);
            }

            /** @var DPDManager $manager */
            $manager = $this->getContainer()->get("dpd_manager");

            /** @var DpdParcelEntity $dpdParcel */
            $dpdParcel = $manager->getDPDEntityById($arg1, 'dpd_parcel');

            $res = $manager->printDPDLabels($dpdParcel, $data);
            if ($res['error'] === true){
                echo $res['message'] . "\n";
                return false;
            }

            echo "Success\n";
            return true;
        }
        else if ($func == 'dpd_parcel_status'){

            /**
             * Dohvaćanje parcel statusa je već objašnjeno
             */

            if (empty($arg1) && !is_int($arg1)) {
                echo 'Please enter id' . "\n";
                return false;
            }

            $data = [];
            if (!empty($arg2)){
                $data = explode(',', $arg2);
            }

            /** @var DPDManager $manager */
            $manager = $this->getContainer()->get("dpd_manager");

            /** @var DpdParcelEntity $dpdParcel */
            $dpdParcel = $manager->getDPDEntityById($arg1, 'dpd_parcel');

            $res = $manager->getDPDParcelStatus($dpdParcel, $data);
            if ($res['error'] === true){
                echo $res['message'];
                return false;
            }

            return true;
        }
        elseif ($func == "dpd_parcel_status_for_all_unfinished") {

            /** @var DPDManager $manager */
            $manager = $this->getContainer()->get("dpd_manager");

            $data = $manager->getUnfinishedDPDParcels();

            if(empty($data)){
                return true;
            }

            $res = $manager->getDPDParcelStatus(null, $data);
            if ($res['error'] === true){
                echo $res['message'];
                return false;
            }

            return true;
        }
        else {
            throw new \Exception("Command type missing: ".json_encode($input->getArguments()));
        }

        return true;
    }
}
