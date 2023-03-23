<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;

class HnbApiManager extends AbstractBaseManager
{
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;

    public function initialize()
    {
        parent::initialize();
        $this->databaseContext = $this->getContainer()->get("database_context");
    }

    /**
     * @return bool|string
     */
    private function getExchangeRates()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://api.hnb.hr/tecajn/v2",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET"
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $response = json_decode($response, true);

        return $response;
    }

    /**
     * @param $date
     * @return bool|mixed
     */
    private function getExchangeRateForDate($date)
    {
        $q = "SELECT * FROM exchange_rate_entity
                WHERE DATE(date_of_application) = '{$date}'
                ORDER BY id DESC
                LIMIT 1;";
        return $this->databaseContext->getSingleEntity($q);
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function syncExchangeRates()
    {
        $q = "";

        $dateNow = new \DateTime();
        $today = $dateNow->format("Y-m-d");

        $data = $this->getExchangeRates();
        if (!empty($data)) {
            foreach ($data as $d) {
                if (isset($d["broj_tecajnice"])) {

                    $result = $this->getExchangeRateForDate($today);
                    if (empty($result)) {
                        $q .= "INSERT IGNORE INTO exchange_rate_entity (
                            entity_type_id, 
                            attribute_set_id, 
                            created, 
                            modified, 
                            entity_state_id, 
                            exchange_rate_id, 
                            date_of_application, 
                            currency_from_code, 
                            currency_from_id, 
                            buying_rate, 
                            median_rate, 
                            selling_rate, 
                            currency_to_code, 
                            currency_to_id
                        ) VALUES (
                            (
                                SELECT entity_type_id FROM attribute_set
                                WHERE attribute_set_code = 'exchange_rate'
                            ),
                            (
                                SELECT id FROM attribute_set
                                WHERE attribute_set_code = 'exchange_rate'
                            ),
                            NOW(),
                            NOW(),
                            1,
                            '{$d['broj_tecajnice']}',
                            '{$d['datum_primjene']}',
                            '{$d['sifra_valute']}',
                            (
                                SELECT id
                                FROM currency_entity
                                WHERE code = '{$d['valuta']}'
                            ),
                            REPLACE('{$d['kupovni_tecaj']}', ',', '.') / {$d['jedinica']},
                            REPLACE('{$d['srednji_tecaj']}', ',', '.') / {$d['jedinica']},
                            REPLACE('{$d['prodajni_tecaj']}', ',', '.') / {$d['jedinica']},
                            '191',
                            1
                        );\n";
                    } else {
                        $q .= "UPDATE exchange_rate_entity SET
                                modified = NOW(),
                                exchange_rate_id = '{$d['broj_tecajnice']}',
                                buying_rate = REPLACE('{$d['kupovni_tecaj']}', ',', '.') / {$d['jedinica']},
                                median_rate = REPLACE('{$d['srednji_tecaj']}', ',', '.') / {$d['jedinica']},
                                selling_rate = REPLACE('{$d['prodajni_tecaj']}', ',', '.') / {$d['jedinica']}
                            WHERE currency_from_code = '{$d['sifra_valute']}'
                            AND currency_from_id =
                                (
                                    SELECT id
                                    FROM currency_entity
                                    WHERE code = '{$d['valuta']}'
                                )
                            AND currency_to_code = '191'
                            AND currency_to_id = 1
                            AND DATE(date_of_application) = '{$d['datum_primjene']}';\n";
                    }
                }
            }

            if (!empty($q)) {
                $this->databaseContext->executeNonQuery($q);
            }
        }

        return true;
    }

    /**
     * @param $code
     * @return bool|mixed
     */
    public function getCurrencyByCode($code)
    {
        $q = "SELECT * FROM currency_entity
            WHERE code = '{$code}';";

        return $this->databaseContext->getSingleEntity($q);
    }

    /**
     * @return bool
     */
    public function syncCurrencies()
    {
        $q = "";

        $data = $this->getExchangeRates();
        if (!empty($data)) {
            foreach ($data as $d) {
                if (isset($d["valuta"])) {

                    $result = $this->getCurrencyByCode($d["valuta"]);
                    if (empty($result)) {
                        $q .= "INSERT IGNORE INTO currency_entity (
                                entity_type_id, 
                                attribute_set_id, 
                                created, 
                                modified, 
                                entity_state_id, 
                                code
                            ) VALUES (
                                (
                                    SELECT entity_type_id FROM attribute_set
                                    WHERE attribute_set_code = 'currency'
                                ),
                                (
                                    SELECT id FROM attribute_set
                                    WHERE attribute_set_code = 'currency'
                                ),
                                NOW(),
                                NOW(),
                                1,
                                '{$d['valuta']}'
                            );\n";
                    }
                }
            }

            if (!empty($q)) {
                $this->databaseContext->executeNonQuery($q);
            }
        }

        return true;
    }
}
