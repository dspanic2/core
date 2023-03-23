<?php
/**
 * Created by PhpStorm.
 * User: Borislav
 * Date: 12.4.2018.
 * Time: 13:21
 */

namespace SanitarijeBusinessBundle\Managers;

use Doctrine\Common\Inflector\Inflector;
use ScommerceBusinessBundle\Managers\DefaultScommerceManager;
use Symfony\Component\HttpFoundation\Request;

class SanitarijeScommerceManager extends DefaultScommerceManager
{
    public function initialize()
    {
        parent::initialize();
    }

    public function beforeParseUrl(Request $request)
    {
        $ret = array();
        $ret["data"] = array();

        $requestUri = $request->getUri();

        /**
         * Default wand redirections
         */
        //redirect za kategorije
        if (stripos($requestUri, "proizvodi/") !== false) {
            $ret["redirect_type"] = 301;
            $ret["redirect_url"] = str_ireplace("proizvodi/", "", $requestUri);
            $ret["redirect_url"] = parse_url($ret["redirect_url"])["path"] ?? "";
        }
        // https://sanitarije.eu/Documents/upload/catalog/product/2805/a105-1200-techlist_588f46ffbc33e.pdf
        elseif (stripos($requestUri, "upload/catalog/product") !== false && stripos($requestUri, "pdf") !== false) {

            $tmp = explode("/", $requestUri);
            $pdf = end($tmp);

            if (empty($this->databaseContext)) {
                $this->databaseContext = $this->container->get("database_context");
            }

            $q = "SELECT product_id FROM _data WHERE document LIKE '%{$pdf}%';";
            $res = $this->databaseContext->getAll($q);

            if(empty($res)){
                $ret["redirect_type"] = 301;
                $ret["redirect_url"] = "/404";
            }
            else{

                $q = "SELECT file FROM product_document_entity WHERE product_id = {$res[0]["product_id"]} and entity_state_id = 1;";
                $res = $this->databaseContext->getAll($q);

                if(empty($res)){
                    $ret["redirect_type"] = 301;
                    $ret["redirect_url"] = "/404";
                }
                else{

                    $ret["redirect_type"] = 301;
                    $ret["redirect_url"] = "/Documents/product_document/" . $res[0]["file"];
                }
            }
        }
        // https://sanitarije.eu/upload/catalog/product/2805/thumb/a105-1200-a105-1200-k-skica_588f46ff347f7_525x525r.jpg
        elseif (stripos($requestUri, "upload/catalog/product") !== false && stripos($requestUri, "thumb") !== false) {

            $tmp = explode("/", $requestUri);
            $img = end($tmp);

            if (empty($this->databaseContext)) {
                $this->databaseContext = $this->container->get("database_context");
            }

            $q = "SELECT product_id FROM _data WHERE images LIKE '%{$img}%';";
            $res = $this->databaseContext->getAll($q);

            if(empty($res)){
                $ret["redirect_type"] = 301;
                $ret["redirect_url"] = "/404";
            }
            else{
                $q = "SELECT file FROM product_images_entity WHERE product_id = {$res[0]["product_id"]} and entity_state_id = 1;";
                $res = $this->databaseContext->getAll($q);

                if(empty($res)){
                    $ret["redirect_type"] = 301;
                    $ret["redirect_url"] = "/404";
                }
                else{

                    $ret["redirect_type"] = 301;
                    $ret["redirect_url"] = "/Documents/Products/" . $res[0]["file"];
                }
            }
        }

        return $ret;
    }


    /**
     * @param $query
     * @param int $type
     * @return string
     */
    public function getProductSearchCompositeFilterForCode($query, $type = 1)
    {
        $addonQuery = "";

        if ($type == 1) {
            if (!preg_match('#[^0-9xa-zA-Z \-\.]#', $query)) {
                $addonQuery = " AND (p.id = '{$query}' OR p.ean LIKE '{$query}' OR p.code LIKE '{$query}' OR p.catalog_code LIKE '{$query}') ";
                if (isset($_ENV["USE_READY_FOR_WEBSHOP"]) && $_ENV["USE_READY_FOR_WEBSHOP"]) {
                    $addonQuery .= " AND p.ready_for_webshop = 1 ";
                }
            }
        } elseif ($type == 3) {
            if (!preg_match('#[^0-9xa-zA-Z \-\.]#', $query)) {
                $addonQuery = " AND (p.ean LIKE '{$query}%' OR p.code LIKE '{$query}%' OR p.catalog_code LIKE '{$query}%') ";
                if (isset($_ENV["USE_READY_FOR_WEBSHOP"]) && $_ENV["USE_READY_FOR_WEBSHOP"]) {
                    $addonQuery .= " AND p.ready_for_webshop = 1 ";
                }
            }
        }

        return $addonQuery;
    }
}
