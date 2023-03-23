<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\NumberHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Helpers\UUIDHelper;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\RestManager;
use CrmBusinessBundle\Entity\InvoiceEntity;
use CrmBusinessBundle\Entity\InvoiceFiscalEntity;
use CrmBusinessBundle\Entity\InvoiceItemEntityEntity;
use CrmBusinessBundle\Entity\PaymentEntity;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\PaymentStatusEntity;
use CrmBusinessBundle\Model\InvoiceFiscalResponse;
use http\Env\Request;
use JMS\Serializer\Tests\Fixtures\Order;
use Monolog\Logger;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Class FiscalInvoiceManager
 * @package CrmBusinessBundle\Managers
 *
 * Contains methods used to process fiscalization information for invoices
 * In order to work following parameters must be set in config *
 * fiscal_service_url - url to FINA fiscalization service
 * fina_root_cert_path - path to FINA CA certificate .cer
 * fina_app_cert_path- path to FINA application certificate .pfx
 * fina_app_cert_password -password to the application ceriftificate
 *
 * also following setting must be placed in application
 * company_oib - OIB used in request toward FINA
 *
 *
 */
class FiscalInvoiceManager extends AbstractBaseManager
{
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /** @var Logger $logger */
    protected $logger;

    /**url to FINA fiscalization service*/
    protected $fiscalServiceUrl;
    /**path to FINA application certificate .pfx*/
    protected $finaAppCertPath;
    /**password to the application ceriftificate*/
    protected $finaAppCertPassword;
    /**path to FINA CA certificate .cer*/
    protected $rootCaCertificate;
    protected $certificate;
    protected $privateKeyResource;
    protected $publicCertificateData;

    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;

    protected $UriId;
    protected $XMLRequestType = "RacunZahtjev";
    protected $companyOIB;

    /**
     * @throws \Exception
     */
    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");

        $this->checkForRequiredParameters();

        $this->companyOIB = $this->container->getParameter('fiscal_company_oib');
        $this->fiscalServiceUrl = $this->container->getParameter('fiscal_service_url');
        $this->rootCaCertificate = $this->container->getParameter('fina_root_cert_path');
        $this->finaAppCertPath = $this->container->getParameter('fina_app_cert_path');
        $this->finaAppCertPassword = $this->container->getParameter('fina_app_cert_password');

        $this->readFinaAppCertificate();
        $this->UriId = uniqid();
    }

    /**
     * Create or get exisiting invoice fiscal information for an invoice
     * If invoice is not fiscalized(does not have afiscal invoice identifier)
     * send a request to FINA fiscalization service     *
     * @param InvoiceEntity $invoice
     * @return InvoiceFiscalEntity
     * @throws \Exception
     *
     */
    public function fiscalizeInvoice(InvoiceEntity $invoice)
    {

        $this->initialize();

        /** @var InvoiceFiscalEntity $invoiceFiscal */
        $invoiceFiscal = $this->getInvoiceFiscal($invoice) ?? $this->createFiscalInvoiceEntity($invoice);

        /**check if invoice already has fiscal invoice identitifire which it means it is already fiscalized*/
        if ($invoiceFiscal->getFiscalInvoiceIdentifier() != null) {
            return $invoiceFiscal;
        }

        /**
         * Regenerate XML on next attempts with NANKNADNA DOSTAVA = 1
         */
        if ($invoiceFiscal->getTotalRequestAttempts() >= 1) {
            $invoiceFiscal = $this->regenerateFiscalInvoiceEntity($invoiceFiscal, true);
        }

        $invoiceFiscalResponse = $this->sendInvoiceFiskalRequest($invoiceFiscal);

        if ($invoiceFiscalResponse->isFiscalized()) {
            $invoiceFiscal->setFiscalInvoiceIdentifierReceivedOn(new \DateTime());
            $invoiceFiscal->setFiscalInvoiceIdentifier($invoiceFiscalResponse->getJir());
        }
        $numOfRequests = $invoiceFiscal->getTotalRequestAttempts() ?? 0;

        $invoiceFiscal->setLastServiceResponseBody($invoiceFiscalResponse->getResponseBody());
        $invoiceFiscal->setLastServiceResponseStatus($invoiceFiscalResponse->getResponseCode());
        $invoiceFiscal->setTotalRequestAttempts($numOfRequests + 1);

        $this->entityManager->saveEntity($invoiceFiscal);
        $this->entityManager->refreshEntity($invoiceFiscal);

        return $invoiceFiscal;
    }


    /**
     * Get fiscalization information for invoice
     *
     * @param InvoiceEntity $invoice
     * @return InvoiceFiscalEntity
     * @throws \Exception
     */
    public function getInvoiceFiscal(InvoiceEntity $invoice)
    {
        $etInvoiceFiscal = $this->entityManager->getEntityTypeByCode("invoice_fiscal");

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("invoice.id", "eq", $invoice->getId()));
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var InvoiceFiscalEntity $invoiceFiscal */
        $invoiceFiscal = $this->entityManager->getEntityByEntityTypeAndFilter($etInvoiceFiscal, $compositeFilters);

        return $invoiceFiscal;
    }

    /**
     * Create fiscalization information for invoice
     *
     * @param InvoiceEntity $invoice
     * @return InvoiceFiscalEntity
     * @throws \Exception
     */
    public function createFiscalInvoiceEntity(InvoiceEntity $invoice)
    {

        /**@var InvoiceFiscalEntity $invoiceFiscal */
        $invoiceFiscal = $this->entityManager->getNewEntityByAttributSetName("invoice_fiscal");

        $invoiceFiscal->setInvoice($invoice);
        $invoiceFiscal->setSecurityCode($this->getSecurityCode($invoice));
        $invoiceFiscal->setRequestIdentifier(UUIDHelper::generateUUID());

        $requestRawBody = $this->createRequestRawBody($invoiceFiscal);
        $invoiceFiscal->setXmlRequestRawBody($requestRawBody);

        $signedXmlRequestBody = $this->signRequestRawBody($requestRawBody);
        $invoiceFiscal->setSignedXmlRequestBody($signedXmlRequestBody);

        $this->entityManager->saveEntity($invoiceFiscal);

        return $invoiceFiscal;
    }

    /**
     * @param InvoiceFiscalEntity $invoiceFiscal
     * @param bool $subsequentDelivery
     * @return InvoiceFiscalEntity
     * @throws \Exception
     */
    public function regenerateFiscalInvoiceEntity(InvoiceFiscalEntity $invoiceFiscal, $subsequentDelivery = false)
    {

        $requestRawBody = $this->createRequestRawBody($invoiceFiscal, $subsequentDelivery);
        $invoiceFiscal->setXmlRequestRawBody($requestRawBody);

        $signedXmlRequestBody = $this->signRequestRawBody($requestRawBody);
        $invoiceFiscal->setSignedXmlRequestBody($signedXmlRequestBody);

        $this->entityManager->saveEntityWithoutLog($invoiceFiscal);
        $this->entityManager->refreshEntity($invoiceFiscal);

        return $invoiceFiscal;
    }

    /**
     * @param InvoiceFiscalEntity $invoiceFiscal
     * @return InvoiceFiscalResponse
     */
    protected function sendInvoiceFiskalRequest(InvoiceFiscalEntity $invoiceFiscal)
    {

        $invoiceFiscalResponse = new InvoiceFiscalResponse();

        $signedXMLRequest = $invoiceFiscal->getSignedXmlRequestBody();

        /** cURL uses .pem  type so we check if have already
         * created a .pem certificate from  .cer to .pem,
         * if not create and add to same directory
         */
        $certificateCApem = $this->rootCaCertificate.'.pem';
        if (!file_exists($certificateCApem)) {
            $certificateCAcerContent = file_get_contents($this->rootCaCertificate);
            $certificateCApemContent =
                '-----BEGIN CERTIFICATE-----'.PHP_EOL
                .chunk_split(base64_encode($certificateCAcerContent), 64, PHP_EOL)
                .'-----END CERTIFICATE-----'.PHP_EOL;
            file_put_contents($certificateCApem, $certificateCApemContent);
        }

        $ch = curl_init();


        $options = array(
            CURLOPT_URL => $this->fiscalServiceUrl,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_SSLVERSION => 'CURL_SSLVERSION_TLSv1',
            CURLOPT_POSTFIELDS => $signedXMLRequest,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CAINFO => $certificateCApem,
        );
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);

        if ($response) {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $DOMResponse = new \DOMDocument();
            $DOMResponse->loadXML($response);

            if ($code === 200) {
                $jirNode = $DOMResponse->getElementsByTagName('Jir')->item(0);
                if ($jirNode) {
                    $jir = $jirNode->nodeValue;
                    $jir = trim($jir);
                    $invoiceFiscalResponse->setIsFiscalized(true);
                    $invoiceFiscalResponse->setJir($jir);
                    $invoiceFiscalResponse->setResponseBody($response);
                    $invoiceFiscalResponse->setResponseCode($code);
                }
            } else {
                $SifraGreske = $DOMResponse->getElementsByTagName('SifraGreske')->item(0)->nodeValue;
                $PorukaGreske = $DOMResponse->getElementsByTagName('PorukaGreske')->item(0)->nodeValue;

                $invoiceFiscalResponse->setIsFiscalized(false);
                $invoiceFiscalResponse->setResponseBody($response);
                $invoiceFiscalResponse->setResponseCode($code);

                $this->logger->error(StringHelper::format('Pogreška prilikom fiskalizacije računa ID:{0}- Status: {1}. Poruka: {2}.', $invoiceFiscal->getInvoice()->getId(), $SifraGreske, $PorukaGreske));
            }
        } else {
            $this->logger->error('Pogreška prilikom fiskalizacije računa ID: '.$invoiceFiscal->getInvoice()->getId().' - '.curl_error($ch));
            $invoiceFiscalResponse->setIsFiscalized(false);
        }

        curl_close($ch);

        return $invoiceFiscalResponse;
    }


    /**
     * Prepare/Build raw request XML
     * @param InvoiceFiscalEntity $invoiceFiscalEntity
     * @param Bool $subsequentDelivery
     * @return string
     *
     */
    protected function createRequestRawBody(InvoiceFiscalEntity $invoiceFiscalEntity, $subsequentDelivery = false)
    {
        /**@var InvoiceEntity $invoice */
        $invoice = $invoiceFiscalEntity->getInvoice();
        $invoiceDate = $invoice->getIssueDate()->format('d.m.Y\TH:i:s');
        if ($this->XMLRequestType == 'RacunZahtjev') {
            $ns = 'tns';

            $writer = new \XMLWriter();
            $writer->openMemory();

            $writer->setIndent(4);
            $writer->startElementNs($ns, 'RacunZahtjev', 'http://www.apis-it.hr/fin/2012/types/f73');
            $writer->writeAttribute('Id', $this->UriId);

            $writer->startElementNs($ns, 'Zaglavlje', null);
            $writer->writeElementNs($ns, 'IdPoruke', null, $invoiceFiscalEntity->getRequestIdentifier());
            $writer->writeElementNs($ns, 'DatumVrijeme', null, date('d.m.Y\TH:i:s'));
            $writer->endElement(); /* #Zaglavlje */


            $writer->startElementNs($ns, 'Racun', null);
            $writer->writeElementNs($ns, 'Oib', null, $this->companyOIB);
            $writer->writeElementNs($ns, 'USustPdv', null, '1');
            $writer->writeElementNs($ns, 'DatVrijeme', null, $invoiceDate);
            $writer->writeElementNs($ns, 'OznSlijed', null, 'P'); /* P ili N => P na nivou Poslovnog prostora, N na nivou naplatnog uredaja */


            $writer->startElementNs($ns, 'BrRac', null);
            $writer->writeElementNs($ns, 'BrOznRac', null, $invoice->getIncrementId());
            $writer->writeElementNs($ns, 'OznPosPr', null, $invoice->getInvoiceDeviceCode()->getInvoiceBusinessPlace()->getCode());
            $writer->writeElementNs($ns, 'OznNapUr', null, $invoice->getInvoiceDeviceCode()->getCode());
            $writer->endElement(); /* #BrRac */

            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            $taxTypeTotals = $this->crmProcessManager->prepareTotalsByTaxType($invoice);

            if (empty($taxTypeTotals)) {
                $this->logger->error("FISCALIZATION: missing tax types: ".$invoiceFiscalEntity->getId());
            } else {
                $writer->startElementNs($ns, 'Pdv', null);

                foreach ($taxTypeTotals as $taxPercent => $taxTypeTotal) {
                    $writer->startElementNs($ns, 'Porez', null);
                    $writer->writeElementNs($ns, 'Stopa', null, number_format($taxPercent, 2, '.', ''));
                    $writer->writeElementNs($ns, 'Osnovica', null, number_format($taxTypeTotal["base_price_without_tax"], 2, '.', ''));
                    $writer->writeElementNs($ns, 'Iznos', null, number_format($taxTypeTotal["base_price_tax"], 2, '.', ''));
                    $writer->endElement(); /* #Porez */
                }

                $writer->endElement(); /* #Pdv */
            }

            $writer->writeElementNs($ns, 'IznosUkupno', null, number_format($invoice->getBasePriceTotal(), 2, '.', ''));

            $writer->writeElementNs($ns, 'NacinPlac', null, $invoice->getPaymentType()->getFiscalCode());

            $writer->writeElementNs($ns, 'OibOper', null, $this->companyOIB);

            $writer->writeElementNs($ns, 'ZastKod', null, $invoiceFiscalEntity->getSecurityCode());
            if ($subsequentDelivery) {
                $writer->writeElementNs($ns, 'NakDost', null, '1');
            } else {
                $writer->writeElementNs($ns, 'NakDost', null, '0');
            }

            $writer->endElement(); /* #Racun */

            $writer->endElement(); /* #RacunZahtjev */


            $XMLRequest = $writer->outputMemory();

            return $XMLRequest;
        }

        return false;
    }

    /**
     * Sign $XMLRequest XML with certificate
     * @param $XMLRequest
     * @return string
     * @throws \Exception
     */
    protected function signRequestRawBody($XMLRequest)
    {
        $XMLRequestDOMDoc = new \DOMDocument();
        $XMLRequestDOMDoc->loadXML($XMLRequest);

        $canonical = $XMLRequestDOMDoc->C14N();
        $DigestValue = base64_encode(hash('sha1', $canonical, true));

        $rootElem = $XMLRequestDOMDoc->documentElement;

        $SignatureNode = $rootElem->appendChild(new \DOMElement('Signature'));
        $SignatureNode->setAttribute('xmlns', 'http://www.w3.org/2000/09/xmldsig#');

        $SignedInfoNode = $SignatureNode->appendChild(new \DOMElement('SignedInfo'));
        $SignedInfoNode->setAttribute('xmlns', 'http://www.w3.org/2000/09/xmldsig#');

        $CanonicalizationMethodNode = $SignedInfoNode->appendChild(new \DOMElement('CanonicalizationMethod'));
        $CanonicalizationMethodNode->setAttribute('Algorithm', 'http://www.w3.org/2001/10/xml-exc-c14n#');

        $SignatureMethodNode = $SignedInfoNode->appendChild(new \DOMElement('SignatureMethod'));
        $SignatureMethodNode->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');

        $ReferenceNode = $SignedInfoNode->appendChild(new \DOMElement('Reference'));
        $ReferenceNode->setAttribute('URI', sprintf('#%s', $this->UriId));

        $TransformsNode = $ReferenceNode->appendChild(new \DOMElement('Transforms'));

        $Transform1Node = $TransformsNode->appendChild(new \DOMElement('Transform'));
        $Transform1Node->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');

        $Transform2Node = $TransformsNode->appendChild(new \DOMElement('Transform'));
        $Transform2Node->setAttribute('Algorithm', 'http://www.w3.org/2001/10/xml-exc-c14n#');

        $DigestMethodNode = $ReferenceNode->appendChild(new \DOMElement('DigestMethod'));
        $DigestMethodNode->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');

        $ReferenceNode->appendChild(new \DOMElement('DigestValue', $DigestValue));

        $SignedInfoNode = $XMLRequestDOMDoc->getElementsByTagName('SignedInfo')->item(0);


        $X509Issuer = $this->publicCertificateData['issuer'];
        $X509IssuerName = sprintf('CN=%s,O=%s,C=%s', $X509Issuer['CN'], $X509Issuer['O'], $X509Issuer['C']);
        //  $X509IssuerSerial = $this->publicCertificateData['serialNumber'];
        $X509IssuerSerial = NumberHelper::hexToDecimal($this->publicCertificateData['serialNumberHex']);

        $publicCertificatePureString = str_replace('-----BEGIN CERTIFICATE-----', '', $this->certificate['cert']);
        $publicCertificatePureString = str_replace('-----END CERTIFICATE-----', '', $publicCertificatePureString);

        $SignedInfoSignature = null;

        if (!openssl_sign($SignedInfoNode->C14N(true), $SignedInfoSignature, $this->privateKeyResource, OPENSSL_ALGO_SHA1)) {
            throw new \Exception('Unable to sign the request');
        }

        $SignatureNode = $XMLRequestDOMDoc->getElementsByTagName('Signature')->item(0);
        $SignatureValueNode = new \DOMElement('SignatureValue', base64_encode($SignedInfoSignature));
        $SignatureNode->appendChild($SignatureValueNode);

        $KeyInfoNode = $SignatureNode->appendChild(new \DOMElement('KeyInfo'));

        $X509DataNode = $KeyInfoNode->appendChild(new \DOMElement('X509Data'));
        $X509CertificateNode = new \DOMElement('X509Certificate', $publicCertificatePureString);
        $X509DataNode->appendChild($X509CertificateNode);

        $X509IssuerSerialNode = $X509DataNode->appendChild(new \DOMElement('X509IssuerSerial'));

        $X509IssuerNameNode = new \DOMElement('X509IssuerName', $X509IssuerName);
        $X509IssuerSerialNode->appendChild($X509IssuerNameNode);

        $X509SerialNumberNode = new \DOMElement('X509SerialNumber', $X509IssuerSerial);
        $X509IssuerSerialNode->appendChild($X509SerialNumberNode);

        $envelope = new \DOMDocument();

        $envelope->loadXML('<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body></soapenv:Body></soapenv:Envelope>');

        $envelope->encoding = 'UTF-8';
        $envelope->version = '1.0';

        $XMLRequestTypeNode = $XMLRequestDOMDoc->getElementsByTagName($this->XMLRequestType)->item(0);
        $XMLRequestTypeNode = $envelope->importNode($XMLRequestTypeNode, true);

        $envelope->getElementsByTagName('Body')->item(0)->appendChild($XMLRequestTypeNode);

        /* Final, signed XML request */
        $signedXmlRequest = $envelope->saveXML();

        return $signedXmlRequest;
    }

    /**
     * GENERATE security code based for invoice
     * @param InvoiceEntity $invoice
     * @return null|string
     */
    public function getSecurityCode(InvoiceEntity $invoice)
    {
        $invoiceDate = $invoice->getIssueDate()->format('d.m.Y H:i:s');

        $invoiceNumber = $invoice->getIncrementId();
        $oznakaPoslovnogProstora = $invoice->getInvoiceDeviceCode()->getInvoiceBusinessPlace()->getCode();
        $oznakaNaplatnogUredaja = $invoice->getInvoiceDeviceCode()->getCode();
        $ukupniIznosRacuna = $invoice->getPriceTotal();

        $securityCodedUnsigned = '';

        $securityCodedUnsigned .= $this->companyOIB;
        $securityCodedUnsigned .= $invoiceDate;
        $securityCodedUnsigned .= $invoiceNumber;
        $securityCodedUnsigned .= $oznakaPoslovnogProstora;
        $securityCodedUnsigned .= $oznakaNaplatnogUredaja;
        $securityCodedUnsigned .= $ukupniIznosRacuna;

        $securityCodeSigned = null;

        openssl_sign($securityCodedUnsigned, $securityCodeSigned, $this->privateKeyResource, OPENSSL_ALGO_SHA1);

        $securityCodeSigned = md5($securityCodeSigned);

        return $securityCodeSigned;
    }

    /**
     * Read relevant information from application(.pfx) certificate
     */
    protected function readFinaAppCertificate()
    {
        openssl_pkcs12_read(file_get_contents($this->finaAppCertPath), $this->certificate, $this->finaAppCertPassword);

        $publicCertificate = $this->certificate['cert'];
        $privateKey = $this->certificate['pkey'];

        $this->privateKeyResource = openssl_pkey_get_private($privateKey, $this->finaAppCertPassword);
        $this->publicCertificateData = openssl_x509_parse($publicCertificate);
    }

    /**
     * Check if all required parameters that are required for fiscalization to work are set
     * @throws \Exception
     */
    protected function checkForRequiredParameters()
    {
        if (!$this->container->hasParameter('fiscal_service_url') ||
            !$this->container->hasParameter('fina_root_cert_path') ||
            !$this->container->hasParameter('fina_app_cert_path') ||
            !$this->container->hasParameter('fina_app_cert_password')) {
            throw new \Exception("Missing one or more of required parameters from configuration:\n
                                           fiscal_service_url,fina_root_cert_path,fina_app_cert_path,fina_app_cert_password ");
        }
    }
}
