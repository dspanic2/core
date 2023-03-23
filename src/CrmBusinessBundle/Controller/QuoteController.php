<?php

namespace CrmBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Helpers\NumberHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Interfaces\Entity\IEntityValidation;
use AppBundle\Managers\AppTemplateManager;
use AppBundle\Managers\FormManager;
use AppBundle\Managers\MailManager;
use AppBundle\Managers\PageManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\AddressEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteItemEntity;
use CrmBusinessBundle\Entity\QuoteStatusEntity;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\EmailTemplateManager;
use CrmBusinessBundle\Managers\OrderManager;
use CrmBusinessBundle\Managers\ProductManager;
use CrmBusinessBundle\Managers\QuoteManager;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use AppBundle\Context\AttributeContext;
use Doctrine\Common\Inflector\Inflector;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use AppBundle\Managers\EntityManager;
use Symfony\Component\Config\Definition\Exception\Exception;
use AppBundle\Entity\FileEntity;

class QuoteController extends AbstractController
{
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /**@var AttributeContext $attributeContext */
    protected $attributeContext;
    /**@var QuoteManager $quoteManager */
    protected $quoteManager;
    /**@var OrderManager $orderManager */
    protected $orderManager;
    /**@var ProductManager $productManager */
    protected $productManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var PageManager $pageManager */
    protected $pageManager;
    /** @var MailManager $mailManager */
    protected $mailManager;
    /** @var FormManager $formManager */
    protected $formManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;

    protected function initialize()
    {
        parent::initialize();
        $this->quoteManager = $this->container->get('quote_manager');
        $this->orderManager = $this->container->get('order_manager');
        $this->entityManager = $this->container->get('entity_manager');
        $this->productManager = $this->container->get('product_manager');
        $this->attributeContext = $this->container->get("attribute_context");
    }

    protected function initializeForm($type)
    {
        $factoryManager = $this->getContainer()->get('factory_manager');
        $this->formManager = $factoryManager->loadFormManager($type);
    }

    /**
     * @Route("/quote/save", name="quote_save_form")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $type = "quote";

        $this->initializeForm($type);

        $p = $_POST;

        if(!isset($p["id"]) || empty($p["id"])){
            $p["skip_qty_check"] = 1;
            $p["enable_sale"] = 1;

            if(empty($this->crmProcessManager)){
                $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
            }

            $paymentType = $this->crmProcessManager->getDefaultPaymentType();
            if(!empty($paymentType)){
                $p["payment_type_id"] = $paymentType->getId();
            }

            if(isset($p["account_id"]) && !empty($p["account_id"])){

                if(empty($this->accountManager)){
                    $this->accountManager = $this->getContainer()->get("account_manager");
                }

                /** @var AccountEntity $account */
                $account = $this->accountManager->getAccountById($p["account_id"]);

                /** @var AddressEntity $billingAddress */
                $billingAddress = $account->getBillingAddress();
                if(!empty($billingAddress)){
                    $p["account_billing_address_id"] = $billingAddress->getId();
                    $p["account_billing_city_id"] = $billingAddress->getCity()->getId();
                    $p["account_billing_street"] = $billingAddress->getStreet();
                }

                $shippingAddress = $account->getShippingAddress();
                if(!empty($shippingAddress)){
                    $p["account_shipping_address_id"] = $shippingAddress->getId();
                    $p["account_shipping_city_id"] = $shippingAddress->getCity()->getId();
                    $p["account_shipping_street"] = $shippingAddress->getStreet();
                }

                $p["calculation_type"] = $this->crmProcessManager->getCalculationMethod(null, $account);
            }
        }

        /** @var ProductEntity $entity */
        $entity = $this->formManager->saveFormModel($type, $p);

        if (empty($entity)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('There has been an error')));
        }

        if ($entity->getEntityValidationCollection() != null) {
            /**@var IEntityValidation $firstValidation */
            $firstValidation = $entity->getEntityValidationCollection()[0];

            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans($firstValidation->getTitle()),
                    'message' => $this->translator->trans($firstValidation->getMessage())
                )
            );
        }

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Success'), 'message' => $this->translator->trans('Form has been submitted'), 'entity' => $this->entityManager->entityToArray($entity)));
    }

    /**
     * @Route("/quote/mass_add_to_quote", name="mass_add_to_quote")
     * @Method("POST")
     */
    public function massAddToQuoteAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product ids are missing')));
        }

        $keys = array_keys($p["items"]);
        if (empty($keys)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product type code is missing')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent is not defined')));
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteById($p["parent_entity_id"]);

        if(empty($quote)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Quote does not exist')));
        }

        if(!in_array($quote->getQuoteStatusId(),Array(CrmConstants::QUOTE_STATUS_NEW,CrmConstants::QUOTE_STATUS_WAITING_FOR_CLIENT))){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Quote not in valid status')));
        }

        foreach ($p["items"][$keys[0]] as $productId) {

            /** @var ProductEntity $product */
            $product = $this->productManager->getProductById($productId);

            //TODO EVENTUALNO VALIDATE PRODUCT

            $this->quoteManager->addUpdateProductInQuote($product,$quote,1,true);
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Products successfully added')));
    }

    /**
     * @Route("/quote/add_to_quote", name="add_to_quote")
     * @Method("POST")
     */
    public function addToQuoteAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Product id is missing')));
        }

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent is not defined')));
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteById($p["parent_entity_id"]);

        if(empty($quote)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Quote does not exist')));
        }

        if(!in_array($quote->getQuoteStatusId(),Array(CrmConstants::QUOTE_STATUS_NEW,CrmConstants::QUOTE_STATUS_WAITING_FOR_CLIENT))){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Quote not in valid status')));
        }

        /** @var ProductEntity $product */
        $product = $this->productManager->getProductById($p["id"]);

        //TODO EVENTUALNO VALIDATE PRODUCT

        $ret = $this->quoteManager->addUpdateProductInQuote($product,$quote,1,true);
        if($ret["error"]){
            return new JsonResponse(array('error' => $ret["error"], 'message' => $ret["message"]));
        }

        if(!$quote->getSkipQtyCheck()){
            $quoteData = Array();
            $quoteData["skip_qty_check"] = 1;
            $this->quoteManager->updateQuote($quote,$quoteData);
        }

        return new JsonResponse(array(
            'error' => false,
            'message' => $this->translator->trans('Product successfully added'),
            'quote_item_id' => $ret["quote_item"]->getId() ?? null,
        ));
    }

    /**
     * @Route("/quote/quote_item_change", name="quote_item_change")
     * @Method("POST")
     */
    public function quoteItemChangeModal(){

        $this->initialize();

        $p = $_POST;
        $p = array_map('trim', $p);

        if(!isset($p["quote_item_id"]) || empty($p["quote_item_id"])){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Quote item id missing')));
        }

        /** @var QuoteItemEntity $quoteItem */
        $quoteItem = $this->quoteManager->getQuoteItemById($p["quote_item_id"]);

        if(empty($quoteItem)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Quote item does not exist')));
        }

        $data["error"] = false;
        $data["multiple"] = false;
        $data["quote_item"] = $quoteItem;
        $data["calculation_type"] = strtolower($quoteItem->getCalculationType());

        $data["start_price"] = floatval($quoteItem->getOriginalBasePriceItem());
        $data["final_item_price"] = floatval($quoteItem->getBasePriceItem());
        if($data["calculation_type"] == "vpc"){
            $data["start_price"] = floatval($quoteItem->getOriginalBasePriceItemWithoutTax());
            $data["final_item_price"] = floatval($quoteItem->getBasePriceItemWithoutTax());
        }
        if(floatval($quoteItem->getBasePriceFixedDiscount()) > 0){
            $data["start_price"] = floatval($quoteItem->getBasePriceFixedDiscount());
        }

        if(empty($this->templateManager)){
            $this->templateManager = $this->getContainer()->get("template_manager");
        }

        $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Quote:quote_item_modal.html.twig", $_ENV["DEFAULT_WEBSITE_ID"]), array(
            'data' => $data
        ));

        $html = $this->renderView('AppBundle:Includes:modal.html.twig', Array("html" => $html, "title" => $this->translator->trans("Update quote item prices")));
        if (empty($html)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error opening modal')));
        }

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/quote/quote_item_change_save", name="quote_item_change_save")
     * @Method("POST")
     */
    public function quoteItemChangeModalSave(){

        $this->initialize();

        $p = $_POST;
        $p = array_map('trim', $p);

        if(!isset($p["id"]) || empty($p["id"])){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Quote item id missing')));
        }

        /** @var QuoteItemEntity $quoteItem */
        $quoteItem = $this->quoteManager->getQuoteItemById($p["id"]);

        if(empty($quoteItem)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Quote item does not exist')));
        }

        if(!in_array($quoteItem->getQuote()->getQuoteStatusId(),Array(CrmConstants::QUOTE_STATUS_NEW,CrmConstants::QUOTE_STATUS_WAITING_FOR_CLIENT))){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Quote not in valid status')));
        }

        $p["qty"] = NumberHelper::cleanDecimal($p["qty"]);

        if(empty($this->crmProcessManager)){
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $ret = $this->crmProcessManager->validateQuoteItemQty($quoteItem->getProduct(), $quoteItem->getQuote(), $p["qty"], false, $quoteItem);
        if($ret["error"]){
            return new JsonResponse(array('error' => $ret["error"], 'message' => $ret["message"]));
        }
        $p["qty"] = $ret["qty"];
        $p["percentage_discount_fixed"] = floatval(NumberHelper::cleanDecimal($p["percentage_discount_fixed"]));

        $p["base_price_fixed_discount"] = floatval(NumberHelper::cleanDecimal($p["base_price_fixed_discount"]));

        $account = $quoteItem->getQuote()->getAccount();
        $parentProduct = null;
        if(!empty($quoteItem->getParentItem())){
            $parentProduct = $quoteItem->getParentItem()->getProduct();
        }

        $baseMethod = $this->crmProcessManager->getCalculationMethod($quoteItem->getProduct(), $account, $parentProduct);
        $getPricesMethod = "getProductPrices".$baseMethod;

        if(empty($this->calculationProvider)){
            $this->calculationProvider = $this->getContainer()->get($_ENV["CALCULATION_PROVIDER"]);
        }

        $prices = $this->calculationProvider->{$getPricesMethod}($quoteItem->getProduct(), $account, $parentProduct, false);

        if(floatval($prices["price"]) == floatval($p["base_price_fixed_discount"])){
            $p["base_price_fixed_discount"] = 0;
        }

        $this->quoteManager->createUpdateQuoteItem($p,$quoteItem,false);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Quote item updated')));
    }

    /**
     * @Route("/quote/quote_admin_accept", name="quote_admin_accept")
     * @Method("POST")
     */
    public function quoteAdminAccept(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Failed'),
                    'message' => $this->translator->trans('Invalid quote id'),
                )
            );
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteById($p["id"]);

        if (empty($quote)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Failed'),
                    'message' => $this->translator->trans('This quote doesnt exist anymore'),
                )
            );
        }

        if ($quote->getQuoteStatusId() != CrmConstants::QUOTE_STATUS_NEW) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Failed'),
                    'message' => $this->translator->trans('Status of this quote cannot be changed'),
                )
            );
        }

        if(!$quote->getSkipQtyCheck()){
            $quoteData = Array();
            $quoteData["skip_qty_check"] = 1;
            $this->quoteManager->updateQuote($quote,$quoteData,true);
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $validation = $this->crmProcessManager->validateCustomAdminQuote($quote);
        if (isset($validation["error"]) && $validation["error"]) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Failed'),
                    'message' => $validation["message"] ?? $this->translator->trans('Missing data on quote'),
                )
            );
        }

        /** @var QuoteStatusEntity $quoteStatus */
        $quoteStatus = $this->quoteManager->getQuoteStatusById(CrmConstants::QUOTE_STATUS_ACCEPTED);
        $this->quoteManager->changeQuoteStatus($quote, $quoteStatus);

        return new JsonResponse(
            array(
                'error' => false,
                'title' => $this->translator->trans('Quote accepted'),
                'message' => $this->translator->trans('Quote successfully accepted'),
            )
        );
    }

    /**
     * @Route("/quote/admin_reject", name="quote_admin_reject")
     * @Method("POST")
     */
    public function quoteAdminReject(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Failed'),
                    'message' => $this->translator->trans('Invalid quote id'),
                )
            );
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteById($p["id"]);

        if (empty($quote)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Failed'),
                    'message' => $this->translator->trans('This quote doesnt exist anymore'),
                )
            );
        }

        if ($quote->getQuoteStatusId() != CrmConstants::QUOTE_STATUS_NEW) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Failed'),
                    'message' => $this->translator->trans('Status of this quote cannot be changed'),
                )
            );
        }

        //todo validate, privremeno iskljuceno dok ne vidimo sto sve hoce

        /** @var QuoteStatusEntity $quoteStatus */
        $quoteStatus = $this->quoteManager->getQuoteStatusById(CrmConstants::QUOTE_STATUS_REJECTED);
        $this->quoteManager->changeQuoteStatus($quote, $quoteStatus);

        return new JsonResponse(
            array(
                'error' => false,
                'title' => $this->translator->trans('Quote rejected'),
                'message' => $this->translator->trans('Quote rejected'),
            )
        );
    }

    /**
     * Gets the HTML form send to client modal.
     * @Route("/quote/send_email", name="quote_send_email")
     * @Method("POST")
     */
    public function quoteSendEmail(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["quote_id"]) || empty($p["quote_id"])) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Failed'),
                    'message' => $this->translator->trans('Invalid quote id'),
                )
            );
        }

        if (!isset($p["email_message"]) || empty($p["email_message"])) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Failed'),
                    'message' => $this->translator->trans('Invalid quote id'),
                )
            );
        }
        $message = $p["email_message"];

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteById($p["quote_id"]);
        if (empty($quote)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Failed'),
                    'message' => $this->translator->trans('This quote doesnt exist anymore'),
                )
            );
        }

        $url = $this->getQuotePreviewUrl($request, $quote);

        // Replace quote preview URL token with real value.
        $message = str_replace('{{ URL }}', $url, $message);

        /** @var ContactEntity $contact */
        $contact = $quote->getContact();

        if(empty($contact) || empty($contact->getEmail())){
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Failure'),
                    'message' => $this->translator->trans('Missing customer on quote.'),
                )
            );
        }

        if(empty($this->mailManager)){
            $this->mailManager = $this->container->get("mail_manager");
        }

        $bcc = array(
            'email' => $_ENV["ORDER_EMAIL_RECIPIENT"],
            'name' => $_ENV["ORDER_EMAIL_RECIPIENT"],
        );

        /** @var SStoreEntity $store */
        $store = $quote->getStore();
        if (empty($store)) {
            if (empty($this->routeManager)) {
                $this->routeManager = $this->container->get("route_manager");
            }
            $store = $this->routeManager->getStoreById($_ENV["DEFAULT_STORE_ID"]);
        }

        /** @var EmailTemplateManager $emailTemplateManager */
        $emailTemplateManager = $this->container->get('email_template_manager');
        /** @var EmailTemplateEntity $template */
        $template = $emailTemplateManager->getEmailTemplateByCode("quote_customer");
        if (!empty($template)) {

            if(empty($this->helperManager)){
                $this->helperManager = $this->container->get("helper_manager");
            }

            $templateData = $emailTemplateManager->renderEmailTemplate($quote, $template, $store, Array("message" => $message));

            $templateAttachments = $template->getAttachments();
            if (!empty($templateAttachments)) {
                $attachments = $template->getPreparedAttachments();
            }

            $this->mailManager->sendEmail(array('email' => $contact->getEmail(), 'name' => $contact->getFullName()), null, $bcc, null, $templateData["subject"], "", null, [], $templateData["content"], $attachments ?? [], $store->getId());
        } else {
            $this->mailManager->sendEmail(Array('email' => $contact->getEmail(), 'name' => $contact->getFullName()),null,$bcc,null,$quote->getName(),"","quote_client_email",Array('html' => $message),null,Array(),$store->getId());
        }

        return new JsonResponse(
            array(
                'error' => false,
                'title' => $this->translator->trans('Success'),
                'message' => $this->translator->trans('Quote sent'),
            )
        );
    }

    /**
     * @Route("/quote/quote_preview", name="quote_preview")
     * @Method("POST")
     */
    public function quotePreview(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["quote_id"]) || empty($p["quote_id"])) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Failed'),
                    'message' => $this->translator->trans('Invalid quote id'),
                )
            );
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteById($p["quote_id"]);
        if (empty($quote)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Failed'),
                    'message' => $this->translator->trans('This quote doesnt exist anymore'),
                )
            );
        }

        $url = $this->getQuotePreviewUrl($request, $quote);

        if(empty($url)){
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Failed'),
                    'message' => $this->translator->trans('Could not generate quote url'),
                )
            );
        }

        return new JsonResponse(
            array(
                'error' => false,
                'redirect_url' => $url
            )
        );
    }

    private function getQuotePreviewUrl(Request $request, QuoteEntity $quote)
    {
        if (!isset($_ENV["QUOTE_PREVIEW_URL"]) || empty($_ENV["QUOTE_PREVIEW_URL"])) {
            throw new \Exception("Missing QUOTE_PREVIEW_URL env variable");
        }

        $url = $_ENV["QUOTE_PREVIEW_URL"] . "?q=" . StringHelper::encrypt($quote->getId());

        return $url;
    }

    /**
     * Gets the HTML form send to client modal.
     * @Route("/quote/send_to_client_form", name="send_to_client_form")
     * @Method("POST")
     */
    public function getSendToClientModalAction(Request $request)
    {
        $this->initialize();
        $p = $_POST;

        $session = $request->getSession();

        if (!isset($p["quote_id"]) || empty($p["quote_id"])) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Quote id not received"))
            );
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteById($p["quote_id"]);
        if (empty($quote)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Quote note found")));
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $validation = $this->crmProcessManager->validateCustomAdminQuote($quote);
        if (isset($validation["error"]) && $validation["error"]) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Failed'),
                    'message' => $validation["message"] ?? $this->translator->trans('Missing data on quote'),
                )
            );
        }

        /** @var AppTemplateManager $appTemplateManager */
        $appTemplateManager = $this->getContainer()->get('app_template_manager');
        $emailTemplate = $this->renderView(
            $appTemplateManager->getTemplatePathByBundle("Quote:quote_client_email_template.html.twig", $session->get("current_website_id")),
            []
        );

        $html = $this->renderView(
            "CrmBusinessBundle:Quote:quote_send_to_client_form.html.twig",
            [
                'email_template' => $emailTemplate ?? "",
                'quote_id' => $p["quote_id"],
            ]
        );

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/quote/quote_download", name="quote_download")
     * @Method("POST")
     */
    public function quoteDownload(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteById($p["id"]);
        if (empty($quote)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Quote not found")));
        }

        $file = $this->quoteManager->generateQuoteFile($quote);

        return new JsonResponse(array("error" => false, "file" => $file, "message" => $this->translator->trans("Document generated")));
    }
}
