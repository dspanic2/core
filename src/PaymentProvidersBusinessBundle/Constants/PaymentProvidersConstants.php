<?php

namespace PaymentProvidersBusinessBundle\Constants;

class PaymentProvidersConstants
{
    const PAYMENT_TRANSACTION_STATUS_PREAUTORIZIRANO = 1;
    const PAYMENT_TRANSACTION_STATUS_NAPLACENO = 2;
    const PAYMENT_TRANSACTION_STATUS_STORNO = 3;
    const PAYMENT_TRANSACTION_STATUS_DJELOMICNO_STORNO = 4;

    const PAYPAL_INTENT_CAPTURE = "CAPTURE"; // authorize and capture the funds immediately
    const PAYPAL_INTENT_AUTHORIZE = "AUTHORIZE"; // authorize funds immediately but capture the funds later

    /**
     * After you redirect the customer to the PayPal payment page, a Continue button appears. Use this option
     * when the final amount is not known when the checkout flow is initiated and you want to redirect the customer
     * to the merchant page without processing the payment.
     */
    const PAYPAL_USER_ACTION_CONTINUE = "CONTINUE";

    /**
     * After you redirect the customer to the PayPal payment page, a Pay Now button appears. Use this option
     * when the final amount is known when the checkout is initiated and you want to process the payment immediately
     * when the customer clicks Pay Now.
     */
    const PAYPAL_USER_ACTION_PAY_NOW = "PAY_NOW";

    const BANKART_TRANSACTION_TYPE_DEBIT = "DEBIT";
    const BANKART_TRANSACTION_TYPE_PREAUTHORIZE = "PREAUTHORIZE";
    const BANKART_TRANSACTION_TYPE_CAPTURE = "CAPTURE";
    const BANKART_TRANSACTION_TYPE_REFUND = "REFUND";
    const BANKART_TRANSACTION_TYPE_VOID = "VOID";
    const BANKART_TRANSACTION_TYPE_CHARGEBACK = "CHARGEBACK";
    const BANKART_TRANSACTION_TYPE_CHARGEBACK_REVERSAL = "CHARGEBACK-REVERSAL";
    const BANKART_TRANSACTION_TYPE_REGISTER = "REGISTER";
    const BANKART_TRANSACTION_TYPE_PAYOUT = "PAYOUT";

    const PAYCEK_CALLBACK_STATUS_CREATED = "created";
    const PAYCEK_CALLBACK_STATUS_WAITING_TRANSACTION = "waiting_transaction";
    const PAYCEK_CALLBACK_STATUS_WAITING_CONFIRMATIONS = "waiting_confirmations";
    const PAYCEK_CALLBACK_STATUS_UNDERPAID = "underpaid";
    const PAYCEK_CALLBACK_STATUS_SUCCESSFUL = "successful";
    const PAYCEK_CALLBACK_STATUS_EXPIRED = "expired";
    const PAYCEK_CALLBACK_STATUS_CANCELED = "canceled";

    const PAYCEK_TRANSACTION_STATUS_OK = "ok";
    const PAYCEK_TRANSACTION_STATUS_PAYMENT_EXISTS = "payment_exists";
    // ... ima ih još puno na PayCekAPI.pdf str. 14 ali nas ostali ne zanimaju

    const PAYMENT_TRANSACTION_LOG_TYPE_PREPARE = 1;
    const PAYMENT_TRANSACTION_LOG_TYPE_REQUEST = 2;
    const PAYMENT_TRANSACTION_LOG_TYPE_RESPONSE = 3;

    const MSTART_PROVIDER_CODE = "mstart_provider";
    const LEANPAY_PROVIDER_CODE = "leanpay_provider";
    const KEKSPAY_PROVIDER_CODE = "kekspay_provider";
    const PAYWAY_PROVIDER_CODE = "payway_provider";
}
