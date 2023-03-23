<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Managers\RestManager;
use AppBundle\Models\UpdateModel;

class MailerLiteApiManager extends DefaultIntegrationImportManager
{
    /** @var string $apiUrl */
    private $apiUrl;
    /** @var string $apiKey */
    private $apiKey;
    /** @var string $groupId */
    private $groupId;

    /** @var RestManager $mailerLiteRestManager */
    private $mailerLiteRestManager;

    public function initialize()
    {
        parent::initialize();

        $this->apiUrl = $_ENV["MAILER_LITE_REQUEST_URL"];
        $this->apiKey = $_ENV["MAILER_LITE_API_KEY"];
        $this->groupId = $_ENV["MAILER_LITE_GROUP_ID"];

        $this->mailerLiteRestManager = new RestManager();
        $this->mailerLiteRestManager->CURLOPT_HTTPHEADER = [
            "Content-Type: application/json",
            "X-MailerLite-ApiKey: " . $this->apiKey
        ];
    }

    /**
     * @param $method
     * @param $endpoint
     * @param array $query
     * @param array $body
     * @return bool|mixed|string
     * @throws \Exception
     */
    private function getApiResponse($method, $endpoint, $query = [], $body = [])
    {
        $url = $this->apiUrl . $endpoint;
        if (!empty($query)) {
            $url .= "?" . http_build_query($query);
        }

        $this->mailerLiteRestManager->CURLOPT_CUSTOMREQUEST = $method;
        $this->mailerLiteRestManager->CURLOPT_POST = ($method == "POST");
        if (!empty($body)) {
            $this->mailerLiteRestManager->CURLOPT_POSTFIELDS = json_encode($body);
        }

        $data = $this->mailerLiteRestManager->get($url);
        $code = $this->mailerLiteRestManager->code;

        if ($code != 200) {
            throw new \Exception(json_encode([$this->mailerLiteRestManager, $url, $data], JSON_UNESCAPED_UNICODE));
        }

        return $data;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getSortedSubscribers()
    {
        $types = [
            "active",
            "unsubscribed",
            "bounced",
            "junk",
            "unconfirmed"
        ];

        $ret = [];

        foreach ($types as $type) {
            $data = $this->getSubscribers($type);
            foreach ($data as $key => $d) {
                $ret[strtolower($d["email"])] = $d;
                unset($data[$key]);
            }
        }

        return $ret;
    }

    /**
     * @param $type
     * @return array|bool|string
     * @throws \Exception
     */
    public function getSubscribers($type)
    {
        $params = [];
        $params["offset"] = 0;
        $params["limit"] = 5000;

        $ret = [];

        do {
            $data = $this->getApiResponse("GET", "groups/" . $this->groupId . "/subscribers/" . $type, $params);
            if (!empty($data)) {
                $ret = array_merge($ret, $data);
            }
            $params["offset"] += $params["limit"];
            echo "Fetched page: " . $params["offset"] / $params["limit"] . "\n";

        } while (!empty($data));

        return $ret;
    }

    /**
     * @param array $body
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function subscribeMember($body = [])
    {
        return $this->getApiResponse("POST", "groups/" . $this->groupId . "/subscribers", [], $body);
    }

    /**
     * @param array $body
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function resubscribeMember($body = [])
    {
        $body["resubscribe"] = true;

        return $this->getApiResponse("POST", "groups/" . $this->groupId . "/subscribers", [], $body);
    }

    /**
     * @param $newsletter
     * @return array
     */
    public function getPreparedSubscriber($newsletter)
    {
        return [
            "email" => $newsletter["email"],
            "name" => $newsletter["first_name"],
            "fields" => [
                "last_name" => $newsletter["last_name"]
            ]
        ];
    }

    /**
     * @param string $sortKey
     * @param int $storeId
     * @return array
     */
    public function getExistingNewsletters($sortKey = "id", $storeId = 3)
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
                s.last_name
            FROM (
                SELECT
                    n.id, n.remote_id, n.date_unsubscribed, n.email, n.contact_id,
                    c.first_name, c.last_name
                FROM newsletter_entity n
                LEFT JOIN contact_entity c ON n.contact_id = c.id AND c.entity_state_id = 1
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
     * @param $activeNewsletters
     * @throws \Exception
     */
    public function syncNewsletter($activeNewsletters)
    {
        $remoteMembers = $this->getSortedSubscribers();

        $updateArray = [];

        $i = 0;
        $c = count($activeNewsletters);

        foreach ($activeNewsletters as $activeNewsletter) {

            $email = strtolower($activeNewsletter["email"]);

            echo sprintf("%s (%u/%u)\n", $email, ++$i, $c);

            if (!isset($remoteMembers[$email])) {

                /**
                 * Kontakt ne postoji u provideru
                 */
                echo "\tSending subscriber to MailerLite\n";
                try {
                    $result = $this->subscribeMember($this->getPreparedSubscriber($activeNewsletter));
                } catch (\Exception $e) {
                    /**
                     * Isključi usere za koje je provider vratio error
                     */
                    $newsletterUpdate = new UpdateModel(["id" => $activeNewsletter["id"]]);
                    $newsletterUpdate->add("active", false, false);
                    $updateArray["newsletter_entity"][$newsletterUpdate->getEntityId()] = $newsletterUpdate->getArray();

                    $contactUpdate = new UpdateModel(["id" => $activeNewsletter["contact_id"]]);
                    $contactUpdate->add("newsletter_signup", false, false);
                    $updateArray["contact_entity"][$contactUpdate->getEntityId()] = $contactUpdate->getArray();

                    $this->errorLogManager->logErrorEvent("Failed to send contact to MailerLite", $e->getMessage(), true);
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
                 * Kontakt već postoji u provideru
                 */
                $newsletterUpdate = new UpdateModel(["id" => $activeNewsletter["id"]]);

                if (empty($activeNewsletter["remote_id"])) {
                    /**
                     * Remote id je prazan - potrebno je sinkronizirati sa providerom
                     * Ovo se može dogoditi samo na shopovima gdje su inicijalno ručno importali kontakte iz baze u provider
                     */
                    echo "\tSynchronizing subscriber id from MailerLite\n";
                    $newsletterUpdate->add("remote_id", $remoteMembers[$email]["id"], false)
                        ->add("remote_sync_date", "NOW()", false);
                }

                if ($remoteMembers[$email]["type"] != "active") {

                    if (empty($activeNewsletter["date_unsubscribed"])) {
                        /**
                         * Kontakt je odjavljen
                         */
                        echo "\tSynchronizing subscriber status from MailerLite\n";
                        $newsletterUpdate->add("active", false, false)
                            ->add("remote_sync_date", "NOW()", false)
                            ->add("date_unsubscribed", (\DateTime::createFromFormat("Y-m-d H:i:s",
                                $remoteMembers[$email]["date_updated"],
                                new \DateTimeZone("+0000"))->format("Y-m-d H:i:s")), false);
                    } else {
                        /**
                         * Kontakt je ponovno prijavljen
                         */
                        echo "\tResending subscriber to MailerLite\n";
                        try {
                            $result = $this->resubscribeMember($this->getPreparedSubscriber($activeNewsletter));
                        } catch (\Exception $e) {
                            /**
                             * Isključi usere za koje je provider vratio validation error
                             */
                            $newsletterUpdate = new UpdateModel(["id" => $activeNewsletter["id"]]);
                            $newsletterUpdate->add("active", false, false);
                            $updateArray["newsletter_entity"][$newsletterUpdate->getEntityId()] = $newsletterUpdate->getArray();

                            $contactUpdate = new UpdateModel(["id" => $activeNewsletter["contact_id"]]);
                            $contactUpdate->add("newsletter_signup", false, false);
                            $updateArray["contact_entity"][$contactUpdate->getEntityId()] = $contactUpdate->getArray();

                            $this->errorLogManager->logErrorEvent("Failed to resend contact to MailerLite", $e->getMessage(), true);
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
    }
}
