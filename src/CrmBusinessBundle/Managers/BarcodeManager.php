<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use Doctrine\ORM\Mapping\Cache;
use Skies\QRcodeBundle\Generator\Generator;
use Skies\QRcodeBundle\Twig\Extensions\Barcode;
use Symfony\Component\HttpFoundation\Response;

class BarcodeManager extends AbstractBaseManager
{
    /** @var HelperManager $helperManager */
    protected $helperManager;
    protected $webPath;
    protected $barcodeType;
    protected $countryCode;
    protected $barcodeWidth;
    /** @var CacheManager $cacheManger */
    protected $cacheManger;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $amount
     * @return string
     */
    private function getPaymentAmountForPDF417($amount, $amountLen)
    {
        $amount *= 100;
        return sprintf("%0{$amountLen}.0f", $amount);
    }

    /**
     * @param $length
     * @param $value
     * @param bool $uppercase
     * @return string
     */
    private function getValueForPDF417($length, $value, $uppercase = true)
    {
        $value = mb_ereg_replace("([^0-9A-Za-zČčĆćĐđŠšŽž\,\.\:\-\+\?\'\/\(\)\s])", "", $value);
        if (is_numeric($length)) {
            $value = substr($value, 0, $length);
        }
        if ($uppercase) {
            $value = mb_strtoupper($value);
        }
        return $value . "\n";
    }

    /**
     * @param OrderEntity $order
     * @return mixed
     */
    public function generatePDF417Barcode(OrderEntity $order)
    {
        /** @var AccountEntity $payerAccount */
        $payerAccount = $order->getAccount();

        if (empty($this->cacheManger)) {
            $this->cacheManger = $this->container->get("cache_manager");
        }

        $data = $_ENV["MONEY_TRANSFER_PAYMENT_SLIP"];
        $data = json_decode($data, true)[$order->getStoreId()];

        if (!empty($data["recipient_iban_or_account"]) && strtoupper(substr($data["recipient_iban_or_account"], 0, 2)) == "SI") {
            $this->countryCode = strtoupper(substr($data["recipient_iban_or_account"], 0, 2));
            $this->barcodeType = "qrcode";
            $amountLength = 11;
        } else {
            $this->countryCode = "HR";
            $this->barcodeType = "pdf417";
            $amountLength = 15;
        }

        $paymentAmountPDF417 = $this->getPaymentAmountForPDF417($order->getBasePriceTotal(), $amountLength);
        $data["payment_description"] = sprintf($data["description_of_payment"], $order->getIncrementId());
        $data["reference_number"] = $order->getIncrementId();

        $data["payer_name"] = $payerAccount->getName();
        $data["payer_address"] = $payerAccount->getBillingAddress()->getStreet();
        $data["payer_place"] = $payerAccount->getBillingAddress()->getCity()->getPostalCode() . " " . $payerAccount->getBillingAddress()->getCity()->getName();

        if(empty($this->crmProcessManager)){
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }
        $data = $this->crmProcessManager->modifyMoneyTransferPaymentSlipData($order,$data);

        $this->webPath = $_ENV["WEB_PATH"];
        $pdfHtml = null;

        if ($this->countryCode == "SI") {
            $pdf417data = $this->getValueForPDF417(CrmConstants::QR_LEADING_STYLE, "UPNQR")
                . $this->getValueForPDF417(CrmConstants::QR_PAYERS_IBAN, "")
                . $this->getValueForPDF417(CrmConstants::QR_DEPOSIT, "", "")
                . $this->getValueForPDF417(CrmConstants::QR_WITHDRAWAL, "")
                . $this->getValueForPDF417(CrmConstants::QR_PAYERS_REFERENCE, "")
                . $this->getValueForPDF417(CrmConstants::QR_PAYERS_NAME, $data["payer_name"])
                . $this->getValueForPDF417(CrmConstants::QR_STREET_AND_NO, $data["payer_address"])
                . $this->getValueForPDF417(CrmConstants::QR_PAYERS_CITY, $data["payer_place"])
                . $this->getValueForPDF417(CrmConstants::QR_AMOUNT, $paymentAmountPDF417)
                . $this->getValueForPDF417(CrmConstants::QR_PAYMENT_DATE, "")
                . $this->getValueForPDF417(CrmConstants::QR_URGENT, "")
                . $this->getValueForPDF417(CrmConstants::QR_PURPOSE_CODE, "COST")
                . $this->getValueForPDF417(CrmConstants::QR_PURPOSE_OF_PAYMENT, $data["payment_description"])
                . $this->getValueForPDF417(CrmConstants::QR_PAYMENT_DEADLINE, "")
                . $this->getValueForPDF417(CrmConstants::QR_RECIPIENTS_IBAN, $data["recipient_iban_or_account"])
                . $this->getValueForPDF417(CrmConstants::QR_RECIPIENTS_REFERENCE, $data["recipient_account_model"] . $data["reference_number"])
                . $this->getValueForPDF417(CrmConstants::QR_RECIPIENTS_NAME, $data["recipient_name"])
                . $this->getValueForPDF417(CrmConstants::QR_RECIPIENTS_STREET_AND_NO, $data["recipient_address_street_and_number"])
                . $this->getValueForPDF417(CrmConstants::QR_RECIPIENTS_CITY, $data["recipient_address_postal_number_and_place"]);
            $pdf417data .= $this->getValueForPDF417(CrmConstants::SUM_OF_LENGTHS, strlen($pdf417data));
            $barcodeWidth = CrmConstants::QR_WIDTH;
        } else {

            $pdf417data = $this->getValueForPDF417(CrmConstants::PDF417_ZAGLAVLJE, "HRVHUB30")
                . $this->getValueForPDF417(CrmConstants::PDF417_VALUTA, $data["payment_currency"])
                . $this->getValueForPDF417(CrmConstants::PDF417_IZNOS, $paymentAmountPDF417, false)
                . $this->getValueForPDF417(CrmConstants::PDF417_IME_I_PREZIME_PLATITELJA, $data["payer_name"])
                . $this->getValueForPDF417(CrmConstants::PDF417_ADRESA_PLATITELJA_ULICA_I_BROJ, $data["payer_address"])
                . $this->getValueForPDF417(CrmConstants::PDF417_ADRESA_PLATITELJA_POSTANSKI_BROJ_I_MJESTO, $data["payer_place"])
                . $this->getValueForPDF417(CrmConstants::PDF417_NAZIV_PRIMATELJA, $data["recipient_name"])
                . $this->getValueForPDF417(CrmConstants::PDF417_ADRESA_PRIMATELJA_ULICA_I_BROJ, $data["recipient_address_street_and_number"])
                . $this->getValueForPDF417(CrmConstants::PDF417_ADRESA_PRIMATELJA_POSTANSKI_BROJ_I_MJESTO, $data["recipient_address_postal_number_and_place"])
                . $this->getValueForPDF417(CrmConstants::PDF417_IBAN_ILI_RACUN_PRIMATELJA, $data["recipient_iban_or_account"])
                . $this->getValueForPDF417(CrmConstants::PDF417_MODEL_RACUNA_PRIMATELJA, $data["recipient_account_model"])
                . $this->getValueForPDF417(CrmConstants::PDF417_POZIV_NA_BROJ_PRIMATELJA, $data["reference_number"])
                . $this->getValueForPDF417(CrmConstants::PDF417_SIFRA_NAMJENE, "COST")
                . $this->getValueForPDF417(CrmConstants::PDF417_OPIS_PLACANJA, $data["payment_description"], false);
            $barcodeWidth = CrmConstants::PDF417_WIDTH;

            //TEST DATA
            //$data = "HRVHUB30\nHRK\n000000000159004\nSHIPSHAPE D.O.O.\nAVENIJA DUBROVNIK 15 (ZICE)\n10000 ZAGREB\nTELE2 D.O.O.\nJOSIPA MAROHNIĆA 1\n10000 ZAGREB\nHR4523400091510186599\nHR01\n20573792-7\nOTLC\nUkupan račun za Tele2 usluge\n";

            $pdfHtml = $this->twig->render("CrmBusinessBundle:Includes:pdf417_template_default.html.twig", array(
                "payment_currency" => $data["payment_currency"],
                "payment_amount" => $order->getBasePriceTotal(),
                "payment_description" => $data["payment_description"],
                "reference_number" => $data["reference_number"],
                "payer_name" => $data["payer_name"],
                "payer_address" => $data["payer_address"],
                "payer_place" => $data["payer_place"],
                "recipient_name" => $data["recipient_name"],
                "recipient_address" => $data["recipient_address_street_and_number"],
                "recipient_place" => $data["recipient_address_postal_number_and_place"],
                "recipient_iban" => $data["recipient_iban_or_account"],
                "recipient_model" => $data["recipient_account_model"],
                "barcode_data" => $pdf417data,
                "web_path" => $this->webPath,
                "frontend_url" => $_ENV["SSL"] . "://" . $_ENV["FRONTEND_URL"]
            ));
        }

        $html = $this->twig->render("CrmBusinessBundle:Includes:virman_barcode.html.twig", array(
            "barcode_data" => $pdf417data,
            "web_path" => $this->webPath,
            "barcode_type" => $this->barcodeType,
        ));

        $targetDir = "/Documents/payment_slip/";
        if (!file_exists($this->webPath . $targetDir)) {
            mkdir($this->webPath . $targetDir, 0777, true);
        }

        if (empty($this->helperManager)) {
            $this->helperManager = $this->container->get("helper_manager");
        }

        $fileName = $this->helperManager->nameToFilename($order->getName()) . "_" . $order->getId();

        /**
         * PDF
         */
        if (!empty($pdfHtml)) {

            $snappyPdf = $this->container->get("knp_snappy.pdf");
            $pdf = $snappyPdf->getOutputFromHtml($pdfHtml, array(
                "enable-local-file-access" => true
            ));

            $targetPath = $targetDir . $fileName . ".pdf";
            $targetPath = str_ireplace("//", "/", $targetPath);
            $targetPath = str_ireplace("//", "/", $this->webPath . $targetPath);

            if (file_exists($targetPath)) {
                unlink($targetPath);
            }

            $this->helperManager->saveRawDataToFile($pdf, $targetPath);
        }

        /**
         * Image
         */
        $snappyImage = $this->container->get('knp_snappy.image');
        $jpg = $snappyImage->getOutputFromHtml($html, array(
            "quality" => 100,
            "enable-local-file-access" => true,
            "width"=> $barcodeWidth
        ));

        $targetPath = $targetDir . $fileName . ".jpeg";
        $targetPath = str_ireplace("//", "/", $targetPath);
        $targetPath = str_ireplace("//", "/", $this->webPath . $targetPath);

        if (file_exists($targetPath)) {
            unlink($targetPath);
        }

        $this->helperManager->saveRawDataToFile($jpg, $targetPath);

        $targetPath = str_ireplace($this->webPath,"/",$targetPath);

        return $targetPath;
    }
}
