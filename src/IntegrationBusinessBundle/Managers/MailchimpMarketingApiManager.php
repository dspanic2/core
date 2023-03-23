<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Managers\RestManager;
use AppBundle\Models\UpdateModel;

class MailchimpMarketingApiManager extends DefaultIntegrationImportManager
{
    /** @var string $apiUrl */
    private $apiUrl;
    /** @var string $apiKey */
    private $apiKey;
    /** @var string $listId */
    private $listId;
    /** @var string $storeId */
    private $storeId;

    public function initialize()
    {
        parent::initialize();

        $this->apiUrl = $_ENV["MAILCHIMP_MARKETING_API_URL"];
        $this->apiKey = $_ENV["MAILCHIMP_MARKETING_API_KEY"];
        $this->listId = $_ENV["MAILCHIMP_MARKETING_API_LIST_ID"] ?? null;
        $this->storeId = $_ENV["MAILCHIMP_MARKETING_API_STORE_ID"] ?? null;
    }

    /**
     * @param $data
     * @param string $prefix
     * @return array
     */
    private function dot_flatten($data, $prefix = '')
    {
        $ret = [];

        foreach ($data as $key => $d) {
            if (is_array($d)) {
                if (!empty($prefix)) {
                    $key = $prefix . "." . $key;
                }
                $tmp = $this->dot_flatten($d, $key);
                if (!empty($tmp)) {
                    $ret = array_merge($ret, $tmp);
                }
            } else {
                if (!empty($prefix)) {
                    $d = $prefix . "." . $d;
                }
                $ret[] = $d;
            }
        }

        return $ret;
    }

    /**
     * @param $data
     * @return string
     */
    private function formatFields($data)
    {
        return implode(",", $this->dot_flatten($data));
    }

    /**
     * @param $method
     * @param $endpoint
     * @param array $query
     * @param array $body
     * @return mixed
     * @throws \Exception
     */
    private function getApiResponse($method, $endpoint, $query = [], $body = [])
    {
        $restManager = new RestManager();
        $restManager->CURLOPT_HTTPHEADER = [
            "Authorization: Bearer " . $this->apiKey
        ];
        $restManager->CURLOPT_CUSTOMREQUEST = $method;
        $restManager->CURLOPT_POST = ($method == "POST");

        if (!empty($body)) {
            $body = json_encode($body);
            $restManager->CURLOPT_POSTFIELDS = $body;
        }

        $url = $this->apiUrl . $endpoint;

        if (!empty($query)) {
            $url .= "?" . http_build_query($query);
        }

        $data = $restManager->get($url, false);
        $code = $restManager->code;

        if ($code != 200) {
            throw new \Exception(sprintf("%s error: %u, request: %s, response: %s", $endpoint, $code, $body, $data), 1);
        }
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        $data = json_decode($data, true);

        return $data;
    }

    /**
     * @param string $listId
     * @param array $params
     * @return mixed
     * @throws \Exception
     */
    public function getLists($listId = "", $params = [])
    {
        return $this->getApiResponse("GET", "/lists/" . $listId, $params);
    }

    /**
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function getMembersList($params = [])
    {
        if (!$this->listId) {
            throw new \Exception("MAILCHIMP_MARKETING_API_LIST_ID is not set");
        }

        $params["offset"] = 0;
        $params["count"] = 1000;

        $ret = [];

        do {
            $res = $this->getApiResponse("GET", "/lists/" . $this->listId . "/members", $params);
            $ret = array_merge($ret, $res["members"]);
            $params["offset"] += 1000;
            echo "Fetched page: " . $params["offset"] / 1000 . "\n";
        } while (isset($res["members"]) && !empty($res["members"]));

        return $ret;
    }

    /**
     * @param $email
     * @return mixed
     * @throws \Exception
     */
    public function getSubscriberByEmail($email)
    {
        $fields = [
            "exact_matches" => [
                "members" => [
                    "id",
                    "email_address",
                    "status",
                    "last_changed"
                ]
            ]
        ];

        $res = $this->getApiResponse("GET", "/search-members",
            ["fields" => $this->formatFields($fields), "query" => $email]);

        return $res["exact_matches"]["members"][0] ?? [];
    }

    /**
     * @param $email
     * @param array $body
     * @return mixed
     * @throws \Exception
     */
    public function subscribeMember($email, $body = [])
    {
        if (!$this->listId) {
            throw new \Exception("MAILCHIMP_MARKETING_API_LIST_ID is not set");
        }

        $body["email_address"] = $email;
        if (!isset($body["status"])) {
            $body["status"] = "pending";
        }

        return $this->getApiResponse("POST", "/lists/" . $this->listId . "/members",
            [], $body);
    }

    /**
     * @param $email
     * @return mixed
     * @throws \Exception
     */
    public function unsubscribeMember($email)
    {
        if (!$this->listId) {
            throw new \Exception("MAILCHIMP_MARKETING_API_LIST_ID is not set");
        }

        $subscriberHash = $this->getSubscriberByEmail($email)["id"];

        $fields = [
            "id",
            "email_address",
            "status",
            "last_changed"
        ];

        return $this->getApiResponse("PATCH", "/lists/" . $this->listId . "/members/" . $subscriberHash,
            ["fields" => $this->formatFields($fields)], ["status" => "unsubscribed"]);
    }

    /**
     * @param $email
     * @return mixed
     * @throws \Exception
     */
    public function resubscribeMember($email)
    {
        if (!$this->listId) {
            throw new \Exception("MAILCHIMP_MARKETING_API_LIST_ID is not set");
        }

        $subscriberHash = $this->getSubscriberByEmail($email)["id"];

        $fields = [
            "id",
            "email_address",
            "status",
            "last_changed"
        ];

        return $this->getApiResponse("PATCH", "/lists/" . $this->listId . "/members/" . $subscriberHash,
            ["fields" => $this->formatFields($fields)], ["status" => "subscribed"]);
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getRecentlyUnsubscribedMembers()
    {
        $fields = [
            "members" => [
                "id",
                "email_address",
                "status",
                "last_changed",
                "web_id"
            ]
        ];

        $params = [];
        $params["fields"] = $this->formatFields($fields);
//        $params["unsubscribed_since"] = (new \DateTime("now -1 day", new \DateTimeZone("+0000")))
//            ->format("Y-m-d\TH:i:sP");
        $params["status"] = "unsubscribed";

        return $this->getMembersList($params);
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getStoresList()
    {
        return $this->getApiResponse("GET", "/ecommerce/stores");
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getProductsList()
    {
        if (!$this->storeId) {
            throw new \Exception("MAILCHIMP_MARKETING_API_STORE_ID is not set");
        }

        $fields = [
            "products" => [
                "id",
                "title",
                "handle",
                "url",
                "description",
                "image_url",
                "variants" => [
                    "id",
                    "title",
                    "url",
                    "description",
                    "image_url",
                    "price",
                    "visibility",
                    "updated_at"
                ]
            ]
        ];

        $params = [];
        $params["fields"] = $this->formatFields($fields);
        $params["offset"] = 0;
        $params["count"] = 1000;

        $ret = [];

        do {
            $res = $this->getApiResponse("GET", "/ecommerce/stores/" . $this->storeId . "/products", $params);
            $ret = array_merge($ret, $res["products"]);
            $params["offset"] += 1000;
            echo "Fetched page: " . $params["offset"] / 1000 . "\n";
        } while (isset($res["products"]) && !empty($res["products"]));

        return $ret;
    }

    /**
     * @param string $sortKey
     * @return array
     * @throws \Exception
     */
    public function getSortedProductsList($sortKey = "id")
    {
        $ret = [];

        $data = $this->getProductsList();
        foreach ($data as $key => $d) {
            $ret[$d[$sortKey]] = $d;
            unset($data[$key]);
        }

        return $ret;
    }

    /**
     * @param string $sortKey
     * @param string $status
     * @return array
     * @throws \Exception
     */
    public function getSortedMembersList($sortKey = "email_address", $status = "")
    {
        $ret = [];

        $fields = [
            "members" => [
                "id",
                "email_address",
                "status",
                "last_changed",
                "web_id"
            ]
        ];

        $params = [];
        $params["fields"] = $this->formatFields($fields);
        if (!empty($status)) {
            $params["status"] = $status;
        }

        $data = $this->getMembersList($params);
        foreach ($data as $key => $d) {
            $ret[strtolower($d[$sortKey])] = $d;
            unset($data[$key]);
        }

        return $ret;
    }

    /**
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function addProduct($data)
    {
        if (!$this->storeId) {
            throw new \Exception("MAILCHIMP_MARKETING_API_STORE_ID is not set");
        }

        return $this->getApiResponse("POST", "/ecommerce/stores/" . $this->storeId . "/products", [], $data);
    }

    /**
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function updateProduct($data)
    {
        if (!$this->storeId) {
            throw new \Exception("MAILCHIMP_MARKETING_API_STORE_ID is not set");
        }

        $productId = $data["id"];
        unset($data["id"]);

        return $this->getApiResponse("PATCH", "/ecommerce/stores/" . $this->storeId . "/products/" . $productId, [], $data);
    }

    /**
     * Only pass products to this function that are:
     *
     * active
     * visible
     * ready_for_webshop
     * show_on_store
     *
     * @param $existingProducts
     * @return bool
     * @throws \Exception
     */
    public function syncProducts($existingProducts)
    {
        $existingRemoteProducts = $this->getSortedProductsList("id");

        $i = 0;
        $c = count($existingProducts);

        foreach ($existingProducts as $productId => $existingProduct) {

            echo sprintf("%s (%u/%u)\n", $productId, ++$i, $c);

            $data = $this->prepareProduct($existingProduct);

            if (!isset($existingRemoteProducts[$productId])) {
                /**
                 * Product doesn't exist at Mailchimp, send it
                 */
                echo "\tSending product to Mailchimp\n";
                try {
                    $this->addProduct($data);
                } catch (\Exception $e) {
                    $this->errorLogManager->logErrorEvent("Failed to send product to Mailchimp", $e->getMessage(), true);
                }

            } else {
                /**
                 * Check if product needs updating at Mailchimp
                 */
                if ($existingRemoteProducts[$productId]["title"] != $data["title"] ||
                    //$existingRemoteProducts[$productId]["handle"] != $data["handle"] ||
                    $existingRemoteProducts[$productId]["url"] != $data["url"] ||
                    $existingRemoteProducts[$productId]["description"] != $data["description"] ||
                    $existingRemoteProducts[$productId]["image_url"] != $data["image_url"] ||
                    $existingRemoteProducts[$productId]["variants"][0]["visibility"] != $data["variants"][0]["visibility"] ||
                    bccomp($existingRemoteProducts[$productId]["variants"][0]["price"], $data["variants"][0]["price"], 2) != 0) {

                    echo "\tSynchronizing product to Mailchimp\n";
                    try {
                        $this->updateProduct($data);
                    } catch (\Exception $e) {
                        $this->errorLogManager->logErrorEvent("Failed to synchronize product to Mailchimp", $e->getMessage(), true);
                    }
                }

                unset($existingRemoteProducts[$productId]);
            }
        }

        /**
         * These products were found at Mailchimp but were not provided in the function parameter
         */
        foreach ($existingRemoteProducts as $existingRemoteProduct) {
            /**
             * Set as not visible or delete
             */
            if ($existingRemoteProduct["variants"][0]["visibility"] != "not_visible") {
                $existingRemoteProduct["variants"][0]["visibility"] = "not_visible";
                echo "\tSynchronizing inactive product to Mailchimp\n";
                try {
                    $this->updateProduct($existingRemoteProduct);
                } catch (\Exception $e) {
                    $this->errorLogManager->logErrorEvent("Failed to synchronize inactive product to Mailchimp", $e->getMessage(), true);
                }
            }
        }

        return true;
    }

    /**
     * @param $activeNewsletters
     * @return bool
     * @throws \Exception
     */
    public function syncNewsletter($activeNewsletters)
    {
        $remoteMembers = array_merge($this->getSortedMembersList("email_address"),
            $this->getSortedMembersList("email_address", "archived"));

        $updateArray = [];

        $i = 0;
        $c = count($activeNewsletters);

        foreach ($activeNewsletters as $activeNewsletter) {

            $email = strtolower($activeNewsletter["email"]);

            echo sprintf("%s (%u/%u)\n", $email, ++$i, $c);

            if (!isset($remoteMembers[$email])) {

                /**
                 * Kontakt ne postoji u Mailchimpu
                 */
                echo "\tSending subscriber to Mailchimp\n";
                try {
                    $result = $this->subscribeMember($email, $this->prepareContact($activeNewsletter));
                } catch (\Exception $e) {
                    if ($e->getCode() != 1) {
                        /**
                         * Greška u konekciji ili slično
                         */
                        $this->errorLogManager->logErrorEvent("Failed to send contact to Mailchimp", $e->getMessage(), true);
                    } else {
                        /**
                         * Isključi usere za koje je Mailchimp izbacio validation error
                         */
                        $newsletterUpdate = new UpdateModel(["id" => $activeNewsletter["id"]]);
                        $newsletterUpdate->add("active", false, false);
                        $updateArray["newsletter_entity"][$newsletterUpdate->getEntityId()] = $newsletterUpdate->getArray();
                    }
                    continue;
                }

                if (isset($result["id"]) && !empty($result["id"])) {
                    $newsletterUpdate = new UpdateModel(["id" => $activeNewsletter["id"]]);
                    $newsletterUpdate->add("remote_id", $result["id"], false)
                        ->add("remote_sync_date", "NOW()", false);
                    $updateArray["newsletter_entity"][$newsletterUpdate->getEntityId()] = $newsletterUpdate->getArray();
                }

            } else {
                /**
                 * Kontakt već postoji u Mailchimpu
                 */
                $newsletterUpdate = new UpdateModel(["id" => $activeNewsletter["id"]]);

                if (empty($activeNewsletter["remote_id"])) {
                    /**
                     * Remote id je prazan - potrebno je sinkronizirati sa Mailchimpom
                     * Ovo se može dogoditi samo na shopovima gdje su inicijalno ručno importali kontakte iz baze u Mailchimp
                     */
                    echo "\tSynchronizing subscriber id from Mailchimp\n";
                    $newsletterUpdate->add("remote_id", $remoteMembers[$email]["id"], false)
                        ->add("remote_sync_date", "NOW()", false);
                }

                if ($remoteMembers[$email]["status"] != "subscribed") {

                    if (empty($activeNewsletter["date_unsubscribed"])) {
                        /**
                         * Kontakt je odjavljen
                         */
                        echo "\tSynchronizing subscriber status from Mailchimp\n";
                        $newsletterUpdate->add("active", false, false)
                            ->add("remote_sync_date", "NOW()", false)
                            ->add("date_unsubscribed", (\DateTime::createFromFormat("Y-m-d\TH:i:sP",
                                $remoteMembers[$email]["last_changed"],
                                new \DateTimeZone("+0000"))->format("Y-m-d H:i:s")), false);
                    } else {
                        /**
                         * Kontakt je ponovno prijavljen
                         */
                        echo "\tResending subscriber to Mailchimp\n";
                        try {
                            $result = $this->resubscribeMember($email);
                        } catch (\Exception $e) {
                            if ($e->getCode() != 1) {
                                /**
                                 * Greška u konekciji ili slično
                                 */
                                $this->errorLogManager->logErrorEvent("Failed to resend contact to Mailchimp", $e->getMessage(), true);
                            } else {
                                /**
                                 * Isključi usere za koje je Mailchimp izbacio validation error
                                 */
                                $newsletterUpdate = new UpdateModel(["id" => $activeNewsletter["id"]]);
                                $newsletterUpdate->add("active", false, false);
                                $updateArray["newsletter_entity"][$newsletterUpdate->getEntityId()] = $newsletterUpdate->getArray();
                            }
                            continue;
                        }

                        if (isset($result["id"]) && !empty($result["id"])) {
                            $newsletterUpdate = new UpdateModel(["id" => $activeNewsletter["id"]]);
                            $newsletterUpdate->add("remote_id", $result["id"], false)
                                ->add("remote_sync_date", "NOW()", false)
                                ->add("date_unsubscribed", null, false);
                            $updateArray["newsletter_entity"][$newsletterUpdate->getEntityId()] = $newsletterUpdate->getArray();
                        }
                    }
                }

                if (!empty($newsletterUpdate->getArray())) {
                    $updateArray["newsletter_entity"][$newsletterUpdate->getEntityId()] = $newsletterUpdate->getArray();
                }
            }
        }

        $this->executeUpdateQuery($updateArray);

        return true;
    }

    /**
     * @param int $storeId
     * @return array
     */
    public function getExistingProductsForMailchimp($storeId = 3)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT
                p.id,
                JSON_UNQUOTE(JSON_EXTRACT(p.name, '$.\"" . $storeId . "\"')) AS name,
                JSON_UNQUOTE(JSON_EXTRACT(p.url, '$.\"" . $storeId . "\"')) AS url,
                JSON_UNQUOTE(JSON_EXTRACT(p.description, '$.\"" . $storeId . "\"')) AS description,
                JSON_UNQUOTE(JSON_EXTRACT(p.show_on_store, '$.\"" . $storeId . "\"')) AS show_on_store,
                p.price_retail,
                pi.file
            FROM product_entity p
            JOIN product_images_entity pi ON p.id = pi.product_id
            AND pi.selected = 1
            AND p.is_visible = 1
            AND p.active = 1
            AND p.ready_for_webshop = 1
            HAVING show_on_store = 1;";

        $ret = [];

        $data = $this->databaseContext->getAll($q);
        foreach ($data as $key => $d) {
            $ret[$d["id"]] = $d;
            unset($data[$key]);
        }

        return $ret;
    }

    /**
     * @param string $sortKey
     * @param int $storeId
     * @return array
     */
    public function getExistingNewslettersForMailchimp($sortKey = "id", $storeId = 3)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT 
                s.id,
                s.remote_id,
                s.date_unsubscribed,
                s.email,
                s.contact_id,
                s.first_name,
                s.last_name,
                s.phone,
                s.date_of_birth,
                MAX(IF (s.headquarters = 1, s.address_id, NULL)) AS h_id,
                MAX(IF (s.headquarters = 1, s.street, NULL)) AS h_street,
                MAX(IF (s.headquarters = 1, s.city_name, NULL)) AS h_city_name,
                MAX(IF (s.headquarters = 1, s.postal_code, NULL)) AS h_postal_code,
                MAX(IF (s.headquarters = 1, s.region_name, NULL)) AS h_region_name,
                MAX(IF (s.headquarters = 1, s.country_code, NULL)) AS h_country_code,
                MAX(IF (s.billing = 1, s.address_id, NULL)) AS b_id,
                MAX(IF (s.billing = 1, s.street, NULL)) AS b_street,
                MAX(IF (s.billing = 1, s.city_name, NULL)) AS b_city_name,
                MAX(IF (s.billing = 1, s.postal_code, NULL)) AS b_postal_code,
                MAX(IF (s.billing = 1, s.region_name, NULL)) AS b_region_name,
                MAX(IF (s.billing = 1, s.country_code, NULL)) AS b_country_code
            FROM (
                SELECT
                    n.id, n.remote_id, n.date_unsubscribed, n.email, n.contact_id,
                    c.first_name, c.last_name, c.phone, c.date_of_birth,
                    a.id AS address_id, a.street, a.headquarters, a.billing,
                    c2.name AS city_name, c2.postal_code,
                    r.name AS region_name,
                    c3.code AS country_code
                FROM newsletter_entity n
                LEFT JOIN contact_entity c ON n.contact_id = c.id AND c.entity_state_id = 1
                LEFT JOIN address_entity a ON n.contact_id = a.contact_id AND a.entity_state_id = 1
                LEFT JOIN city_entity c2 ON a.city_id = c2.id AND c2.entity_state_id = 1
                LEFT JOIN region_entity r ON c2.region_id = r.id AND r.entity_state_id = 1
                LEFT JOIN country_entity c3 ON c2.country_id = c3.id AND c3.entity_state_id = 1
                WHERE n.entity_state_id = 1
                AND n.active = 1
                AND n.store_id = {$storeId}
            ) AS s 
            GROUP BY COALESCE(s.contact_id, s.id);";

        $ret = [];

        $data = $this->databaseContext->getAll($q);
        foreach ($data as $key => $d) {
            $ret[$d[$sortKey]] = $d;
            unset($data[$key]);
        }

        return $ret;
    }

    /**
     * @param $product
     * @return array
     */
    private function prepareProduct($product)
    {
        $frontendUrl = $_ENV["SSL"] . "://" . $_ENV["FRONTEND_URL"] . $_ENV["FRONTEND_URL_PORT"];

        $data = [];
        $data["id"] = $product["id"];
        $data["title"] = $product["name"];
        //$data["handle"] = $product["url"];
        $data["url"] = $frontendUrl . "/" . $product["url"];
        $data["description"] = $product["description"];
        if (empty($data["description"]) || $data["description"] == "null") {
            $data["description"] = "";
        }
        $data["image_url"] = $frontendUrl . "/Documents/Products/" . $product["file"];

        $data["variants"][] = $data;
        $data["variants"][0]["price"] = (float)$product["price_retail"];
        $data["variants"][0]["visibility"] = "visible";
        unset($data["variants"][0]["handle"]);

        return $data;
    }

    /**
     * @param $newsletter
     * @return array
     * @throws \Exception
     */
    private function prepareContact($newsletter)
    {
        $data = [];
        $data["status"] = "subscribed";

        if (!empty($newsletter["contact_id"])) {

            $data["merge_fields"]["FNAME"] = $newsletter["first_name"];
            $data["merge_fields"]["LNAME"] = $newsletter["last_name"];

//            if (!empty($newsletter["phone"])) {
//                $data["merge_fields"]["PHONE"] = $newsletter["phone"];
//            }
//            if (!empty($newsletter["date_of_birth"])) {
//                $data["merge_fields"]["BIRTHDAY"] = (\DateTime::createFromFormat("Y-m-d",
//                    $newsletter["date_of_birth"])->format("m/d"));
//            }
//            if (!empty($newsletter["h_id"])) {
//
//                $data["merge_fields"]["ADDRESS"]["addr1"] = $newsletter["h_street"];
//                if (!empty($newsletter["h_city_name"])) {
//                    $data["merge_fields"]["ADDRESS"]["city"] = $newsletter["h_city_name"];
//                }
//                if (!empty($newsletter["h_postal_code"])) {
//                    $data["merge_fields"]["ADDRESS"]["zip"] = $newsletter["h_postal_code"];
//                }
//                if (!empty($newsletter["h_region_name"])) {
//                    $data["merge_fields"]["ADDRESS"]["state"] = $newsletter["h_region_name"];
//                }
//                if (!empty($newsletter["h_country_code"])) {
//                    $data["merge_fields"]["ADDRESS"]["country"] = $newsletter["h_country_code"];
//                }
//                if (!empty($newsletter["b_id"])) {
//                    $data["merge_fields"]["ADDRESS"]["addr2"] = $newsletter["b_street"];
//                }
//
//            } else if (!empty($newsletter["b_id"])) {
//
//                $data["merge_fields"]["ADDRESS"]["addr1"] = $newsletter["b_street"];
//                if (!empty($newsletter["b_city_name"])) {
//                    $data["merge_fields"]["ADDRESS"]["city"] = $newsletter["b_city_name"];
//                }
//                if (!empty($newsletter["b_postal_code"])) {
//                    $data["merge_fields"]["ADDRESS"]["zip"] = $newsletter["b_postal_code"];
//                }
//                if (!empty($newsletter["b_region_name"])) {
//                    $data["merge_fields"]["ADDRESS"]["state"] = $newsletter["b_region_name"];
//                }
//                if (!empty($newsletter["b_country_code"])) {
//                    $data["merge_fields"]["ADDRESS"]["country"] = $newsletter["b_country_code"];
//                }
//            }
        }

        return $data;
    }
}

