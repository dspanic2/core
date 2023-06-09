<?php

namespace CrmBusinessBundle\Constants;

class CrmConstants
{
    const ACCOUNT_ATTRIBUTE_SET_ID = 2;
    const LEAD_ATTRIBUTE_SET_ID = 14;

    const ACCOUNT_TYPE_COMPETITOR = 2;
    const ACCOUNT_TYPE_CUSTOMER = 3;
    const ACCOUNT_TYPE_PARTNER = 6;
    const ACCOUNT_TYPE_RESELLER = 9;
    const ACCOUNT_TYPE_SUPPLIER = 10;
    const ACCOUNT_TYPE_MANUFACTURER = 13;

    const DEFAULT_CURRENCY_RATE_ID = 1;

    const DEFAULT_USER_ROLE_ID = 11;

    const DEFAULT_CORE_LANGUAGE_ID = 1;

    const QUOTE_STATUS_NEW = 1;
    const QUOTE_STATUS_CANCELED = 2;
    const QUOTE_STATUS_ACCEPTED = 3;
    const QUOTE_STATUS_REJECTED = 4;
    const QUOTE_STATUS_WAITING_FOR_CLIENT = 5;

    const ORDER_STATE_NEW = 1;
    const ORDER_STATE_IN_PROCESS = 2;
    const ORDER_STATE_COMPLETED = 3;
    const ORDER_STATE_CANCELED = 4;
    const ORDER_STATE_REVERSAL = 5;
    const ORDER_STATE_READY_FOR_PICKUP = 7;
    const ORDER_STATE_IN_PAYMENT= 8;

    const ORDER_RETURN_STATE_NEW = 1;
    const ORDER_RETURN_STATE_IN_PROCESS = 2;
    const ORDER_RETURN_STATE_CONFIRMED = 3;
    const ORDER_RETURN_STATE_DECLINED = 4;
    const ORDER_RETURN_STATE_COMPLETED = 5;
    const ORDER_RETURN_STATE_CANCELED = 6;

    const LEAD_STATUS_CONTACTED  = 3;
    const LEAD_STATUS_LOST = 5;
    const LEAD_STATUS_NOT_CONTACTED  = 6;
    const LEAD_STATUS_OFFER_SENT  = 9;
    const LEAD_STATUS_ONLINE_PRE_SALES = 10;

    const ADVERTISEMENT = 1;
    const COLD_CALL = 2;
    const EMPLOYEE_REGEFERAL  = 3;
    const EXTERAL_REFERAL = 4;
    const ONLIE_STORE  = 5;
    const PUBLIC_RELATIONS = 6;
    const TRADE_SHOW = 7;
    const WEB = 8;
    const SOCIAL_MEDIA = 9;

    const PAYMENT_TRANSACTION_STATUS_PREAUTHORISED = 1;
    const PAYMENT_TRANSACTION_STATUS_COMPLETED = 2;
    const PAYMENT_TRANSACTION_STATUS_REVERSAL = 3;
    const PAYMENT_TRANSACTION_STATUS_CANCELED = 3;

    const PAYMENT_TYPE_CARD = 1;
    const PAYMENT_TYPE_PAYPAL = 5;
    const PAYMENT_TYPE_VIRMAN = 3;
    const PAYMENT_TYPE_LEANPAY = 10;

    const DEFAULT_INVOICE_DEVICE_CODE = 1;

    const ROLE_COMMERCE_ADMIN = "ROLE_COMMERCE_ADMIN";

    const DISCOUNT_COUPON_APPLY_BIGGER_DISCOUNT = 1;
    const DISCOUNT_COUPON_APPLY_ON_DISCOUNT_PRICE = 2;
    const DISCOUNT_COUPON_APPLY_ON_ORIGINAL_PRICE = 3;

    const PRODUCT_TYPE_SIMPLE = 1;
    const PRODUCT_TYPE_CONFIGURABLE = 2;
    const PRODUCT_TYPE_BUNDLE = 3;
    const PRODUCT_TYPE_BUNDLE_WAND = 4;
    const PRODUCT_TYPE_CONFIGURABLE_BUNDLE = 6;

    const PRODUCT_RELATION_TYPE_RELATED = 1;
    const PRODUCT_RELATION_TYPE_UPSELL = 2;
    const PRODUCT_RELATION_TYPE_CROSSELL = 3;
    const PRODUCT_RELATION_TYPE_BUNDLE = 4;
    const PRODUCT_RELATION_TYPE_CONFIGURABLE = 5;
    const PRODUCT_RELATION_TYPE_CONFIGURABLE_BUNDLE = 6;

    /**
     * PDF417 field max lengths
     */
    const PDF417_ZAGLAVLJE = 8;
    const PDF417_VALUTA = 3;
    const PDF417_IZNOS = 15;
    const PDF417_IME_I_PREZIME_PLATITELJA = 30;
    const PDF417_ADRESA_PLATITELJA_ULICA_I_BROJ = 27;
    const PDF417_ADRESA_PLATITELJA_POSTANSKI_BROJ_I_MJESTO = 27;
    const PDF417_NAZIV_PRIMATELJA = 25;
    const PDF417_ADRESA_PRIMATELJA_ULICA_I_BROJ = 25;
    const PDF417_ADRESA_PRIMATELJA_POSTANSKI_BROJ_I_MJESTO = 27;
    const PDF417_IBAN_ILI_RACUN_PRIMATELJA = 21;
    const PDF417_MODEL_RACUNA_PRIMATELJA = 4;
    const PDF417_POZIV_NA_BROJ_PRIMATELJA = 22;
    const PDF417_SIFRA_NAMJENE = 4;
    const PDF417_OPIS_PLACANJA = 35;
    const PDF417_WIDTH = 900;

    /**
     * QR code fields
     */
    const QR_LEADING_STYLE = 5;
    const QR_PAYERS_IBAN = 19;
    const QR_DEPOSIT = 1;
    const QR_WITHDRAWAL = 1;
    const QR_PAYERS_REFERENCE = 26;
    const QR_PAYERS_NAME = 33;
    const QR_STREET_AND_NO = 33;
    const QR_PAYERS_CITY = 33;
    const QR_AMOUNT = 11;
    const QR_PAYMENT_DATE = 10;
    const QR_URGENT = 1;
    const QR_PURPOSE_CODE = 4;
    const QR_PURPOSE_OF_PAYMENT = 42;
    const QR_PAYMENT_DEADLINE = 10;
    const QR_RECIPIENTS_IBAN = 34;
    const QR_RECIPIENTS_REFERENCE = 26;
    const QR_RECIPIENTS_NAME = 33;
    const QR_RECIPIENTS_STREET_AND_NO = 33;
    const QR_RECIPIENTS_CITY = 33;
    const SUM_OF_LENGTHS = 3;
    const QR_WIDTH = 250;


    const RELATED_PRODUCT_RELATION_TYPE_ID = 1;
    const CONFIGURABLE_PRODUCT_RELATION_TYPE_ID = 5;
}
