<?php

// php bin/console paymenthelper:run paypal_get_order_details 4PH02703XK358725S
// php bin/console paymenthelper:run paypal_get_payment_details 8AL77895CU699911C
// php bin/console paymenthelper:run paypal_reauthorize_payment 8AL77895CU699911C
// php bin/console paymenthelper:run bankart_get_transaction_status 8fb763d8112d6ac9604b58b56c8cb42c
// php bin/console paymenthelper:run paycek_open_payment 55
// php bin/console paymenthelper:run paycek_get_payment makUIHgEkC1qWLojwPoCHt9E4JBM1XWGBQbYLb49l0vi
// php bin/console paymenthelper:run paycek_cancel_payment makUIHgEkC1qWLojwPoCHt9E4JBM1XWGBQbYLb49l0vi
// php bin/console paymenthelper:run paycek_get_api_mac
// php bin/console paymenthelper:run mstart_check_status 11
// php bin/console paymenthelper:run mstart_complete_transaction 19
// php bin/console paymenthelper:run mstart_refund_transaction 19 50
// php bin/console paymenthelper:run kekspay_refund_transaction 25 50
// php bin/console paymenthelper:run import_leanpay_installments



namespace PaymentProvidersBusinessBundle\Command;

use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Managers\QuoteManager;
use PaymentProvidersBusinessBundle\Managers\PaymentTransactionManager;
use PaymentProvidersBusinessBundle\PaymentProviders\BankartProvider;
use PaymentProvidersBusinessBundle\PaymentProviders\KeksPayProvider;
use PaymentProvidersBusinessBundle\PaymentProviders\MstartProvider;
use PaymentProvidersBusinessBundle\PaymentProviders\PayCekProvider;
use PaymentProvidersBusinessBundle\PaymentProviders\PayPalProvider;
use PaymentProvidersBusinessBundle\PaymentProviders\LeanpayProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PaymentProvidersCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName("paymenthelper:run")
            ->SetDescription("Helper functions")
            ->AddArgument("type", InputArgument::OPTIONAL, " which function ")
            ->AddArgument('arg1', InputArgument :: OPTIONAL, " which arg1 ")
            ->AddArgument('arg2', InputArgument :: OPTIONAL, " which arg2 ");
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

        $func = $input->getArgument("type");
        if ($func == "paypal_get_order_details") {

            /** @var PayPalProvider $paypalProvider */
            $paypalProvider = $this->getContainer()->get("paypal_provider");

            $accessToken = $paypalProvider->getAccessToken();

            $arg1 = $input->getArgument("arg1");

            $res = $paypalProvider->getOrderDetails($accessToken, $arg1);

            dump($res);
        } else if ($func == "paypal_get_payment_details") {

            /** @var PayPalProvider $paypalProvider */
            $paypalProvider = $this->getContainer()->get("paypal_provider");

            $accessToken = $paypalProvider->getAccessToken();

            $arg1 = $input->getArgument("arg1");

            $res = $paypalProvider->getAuthorizedPaymentDetails($accessToken, $arg1);

            dump($res);
        } else if ($func == "paypal_reauthorize_payment") {

            /** @var PayPalProvider $paypalProvider */
            $paypalProvider = $this->getContainer()->get("paypal_provider");

            $accessToken = $paypalProvider->getAccessToken();

            $arg1 = $input->getArgument("arg1");

            $res = $paypalProvider->reauthorizeAuthorizedPayment($accessToken, $arg1);

            dump($res);
        } else if ($func == "bankart_get_transaction_status") {

            /** @var BankartProvider $provider */
            $provider = $this->getContainer()->get("bankart_provider");

            $arg1 = $input->getArgument("arg1");

            $ret = $provider->apiGetPaymentStatus($arg1);

            dump($ret);
        } else if ($func == "paycek_open_payment") {

            /** @var PayCekProvider $provider */
            $provider = $this->getContainer()->get("paycek_provider");

            $arg1 = $input->getArgument("arg1");

            /** @var QuoteManager $quoteManager */
            $quoteManager = $this->getContainer()->get("quote_manager");

            /** @var QuoteEntity $quote */
            $quote = $quoteManager->getQuoteById($arg1);

            $ret = $provider->generatePaymentUrlForQuote($quote);

            dump($ret);
        } else if ($func == "paycek_get_payment") {

            /** @var PayCekProvider $provider */
            $provider = $this->getContainer()->get("paycek_provider");

            $arg1 = $input->getArgument("arg1");

            $ret = $provider->getPaymentByPaymentCode($arg1);

            dump($ret);
        } else if ($func == "paycek_cancel_payment") {

            /** @var PayCekProvider $provider */
            $provider = $this->getContainer()->get("paycek_provider");

            $arg1 = $input->getArgument("arg1");

            $ret = $provider->cancelPaymentByPaymentCode($arg1);

            dump($ret);
        } else if ($func == "paycek_get_api_mac") {

            /** @var PayCekProvider $provider */
            $provider = $this->getContainer()->get("paycek_provider");

            $apiMac = $provider->getApiMac(
                "zNzBJGvnr7Gq5e_TAMXg3Za63MhvNof0fUIxDSsf4X6A",
                "iZ7VupryIax-b-qnfNB0VZofOB_FeH3jRKD-6c4sEhlQ",
                "1639136505963",
                "GET",
                "/api/paycek_callback",
                "",
                ""
            );

            dump($apiMac);
        }
        else if ($func == "mstart_check_status") {

            /** @var MstartProvider $provider */
            $provider = $this->getContainer()->get("mstart_provider");

            /** @var PaymentTransactionManager $paymentTransactionManager */
            $paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");

            $arg1 = $input->getArgument("arg1");
            if(empty($arg1)){
                throw new \Exception("Missing payemt transaction id");
            }

            /** @var PaymentTransactionEntity $paymentTransaction */
            $paymentTransaction = $paymentTransactionManager->getPaymentTransactionById($arg1);

            if(empty($paymentTransaction)){
                throw new \Exception("Missing payemt transaction");
            }

            $res = $provider->checkStatusByPaymentTransaction($paymentTransaction);

            dump($res);
        }
        else if ($func == "mstart_complete_transaction") {

            /** @var MstartProvider $provider */
            $provider = $this->getContainer()->get("mstart_provider");

            /** @var PaymentTransactionManager $paymentTransactionManager */
            $paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");

            $arg1 = $input->getArgument("arg1");
            if(empty($arg1)){
                throw new \Exception("Missing payemt transaction id");
            }

            /** @var PaymentTransactionEntity $paymentTransaction */
            $paymentTransaction = $paymentTransactionManager->getPaymentTransactionById($arg1);

            if(empty($paymentTransaction)){
                throw new \Exception("Missing payemt transaction");
            }

            $res = $provider->completeTransaction($paymentTransaction);

            dump($res);
        }
        else if ($func == "mstart_refund_transaction") {

            /** @var MstartProvider $provider */
            $provider = $this->getContainer()->get("mstart_provider");

            /** @var PaymentTransactionManager $paymentTransactionManager */
            $paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");

            $arg1 = $input->getArgument("arg1");
            if(empty($arg1)){
                throw new \Exception("Missing payemt transaction id");
            }

            /** @var PaymentTransactionEntity $paymentTransaction */
            $paymentTransaction = $paymentTransactionManager->getPaymentTransactionById($arg1);

            if(empty($paymentTransaction)){
                throw new \Exception("Missing payemt transaction");
            }

            $res = $provider->refundTransaction($paymentTransaction);

            dump($res);
        }
        else if ($func == "kekspay_refund_transaction") {

            /** @var KeksPayProvider $provider */
            $provider = $this->getContainer()->get("kekspay_provider");

            /** @var PaymentTransactionManager $paymentTransactionManager */
            $paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");

            $arg1 = $input->getArgument("arg1");
            if(empty($arg1)){
                throw new \Exception("Missing payment transaction id");
            }

            $arg2 = $input->getArgument("arg2");

            /** @var PaymentTransactionEntity $paymentTransaction */
            $paymentTransaction = $paymentTransactionManager->getPaymentTransactionById($arg1);

            if(empty($paymentTransaction)){
                throw new \Exception("Missing payemt transaction");
            }

            $res = $provider->refundTransaction($paymentTransaction, $arg2);

            dump($res);
        }
        else if ($func == "import_leanpay_installments") {
            /** @var LeanpayProvider $provider */
            $provider = $this->getContainer()->get("leanpay_provider");

            $res = $provider->importInstallmentPlans();

            dump($res);
        }
        else {
            throw new \Exception("Command type missing: " . json_encode($input->getArguments()));
        }

        return false;
    }
}
