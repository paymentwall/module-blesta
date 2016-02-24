<?php

if (!class_exists('Paymentwall_Config'))
    include(getcwd() . '/components/gateways/lib/paymentwall-php/lib/paymentwall.php');

/**
 * Paymentwall
 *
 * @copyright Copyright (c) 2015, Paymentwall, Inc.
 * @link http://www.paymentwall.com/ Paymentwall
 */
class Paymentwall extends NonmerchantGateway
{
    const PW_PAYMENT_CREDIT = "credit";
    const PW_PAYMENT_NORMAL = "normal";

    private $gwId;

    /**
     * @var string The version of this gateway
     */
    private static $version = "1.0.0";
    /**
     * @var string The authors of this gateway
     */
    private static $authors = array(array('name' => "Paymentwall, Inc.", 'url' => "https://www.paymentwall.com"));

    public function __construct()
    {
        // Load components required by this module
        Loader::loadComponents($this, array("Input"));

        // Load the language required by this module
        Language::loadLang("paymentwall", null, dirname(__FILE__) . DS . "language" . DS);

    }

    /**
     * Initial Paymentwall settings
     */
    public function initPaymentwallConfigs()
    {
        Paymentwall_Config::getInstance()->set(array(
            'api_type' => Paymentwall_Config::API_GOODS,
            'public_key' => $this->meta['project_key'],
            'private_key' => $this->meta['secret_key']
        ));
    }

    /**
     * Returns the name of this gateway
     *
     * @return string The common name of this gateway
     */
    public function getName()
    {
        return Language::_("Paymentwall.name", true);
    }

    /**
     * Returns the version of this gateway
     *
     * @return string The current version of this gateway
     */
    public function getVersion()
    {
        return self::$version;
    }

    /**
     * Returns the name and URL for the authors of this gateway
     *
     * @return array The name and URL of the authors of this gateway
     */
    public function getAuthors()
    {
        return self::$authors;
    }

    /**
     * Return all currencies activated
     *
     * @return array A numerically indexed array containing all currency codes (ISO 4217 format) this gateway supports
     */
    public function getCurrencies()
    {
        $currencies = array();
        $record = new Record();
        $result = $record->select("code")->from("currencies")->fetchAll();

        foreach ($result as $currency) {
            $currencies[] = $currency->code;
        }

        unset($record);
        return $currencies;
    }

    /**
     * Sets the currency code to be used for all subsequent payments
     *
     * @param string $currency The ISO 4217 currency code to be used for subsequent payments
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    public function getSignupUrl()
    {
        return "https://api.paymentwall.com/pwaccount/signup?source=blesta&mode=merchant";
    }

    /**
     * Create and return the view content required to modify the settings of this gateway
     *
     * @param array $meta An array of meta (settings) data belonging to this gateway
     * @return string HTML content containing the fields to update the meta data for this gateway
     */
    public function getSettings(array $meta = null)
    {
        $this->view = $this->makeView("settings", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));
        // Load the helpers required for this view
        Loader::loadHelpers($this, array("Form", "Html"));
        $this->view->set("meta", $meta);

        return $this->view->fetch();
    }

    /**
     * Validates the given meta (settings) data to be updated for this gateway
     *
     * @param array $meta An array of meta (settings) data to be updated for this gateway
     * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
     */
    public function editSettings(array $meta)
    {
        // Verify meta data is valid
        $rules = array(
            'project_key' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("Paymentwall.!error.project_key.valid", true),
                    'post_format' => 'trim'
                )
            ),
            'secret_key' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("Paymentwall.!error.secret_key.valid", true),
                    'post_format' => 'trim'
                )
            ),
            'widget_code' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("Paymentwall.!error.widget_code.valid", true),
                    'post_format' => 'trim'
                )
            ),
            'test_mode' => array(
                'valid' => array(
                    'rule' => array("in_array", array('true', 'false')),
                    'message' => Language::_("Paymentwall.!error.test_mode.valid", true)
                )
            ),
        );

        // Set checkbox if not set
        if (!isset($meta['test_mode']))
            $meta['test_mode'] = "false";

        $this->Input->setRules($rules);

        // Validate the given meta data to ensure it meets the requirements
        $this->Input->validates($meta);
        // Return the meta data, no changes required regardless of success or failure for this gateway
        return $meta;
    }

    /**
     * Returns an array of all fields to encrypt when storing in the database
     *
     * @return array An array of the field names to encrypt when storing in the database
     */
    public function encryptableFields()
    {
        return array("project_key", "secret_key", "widget_code");
    }

    /**
     * Sets the meta data for this particular gateway
     *
     * @param array $meta An array of meta data to set for this gateway
     */
    public function setMeta(array $meta = null)
    {
        $this->meta = $meta;
    }

    /**
     * Returns all HTML markup required to render an authorization and capture payment form
     *
     * @param array $contact_info An array of contact info including:
     *    - id The contact ID
     *    - client_id The ID of the client this contact belongs to
     *    - user_id The user ID this contact belongs to (if any)
     *    - contact_type The type of contact
     *    - contact_type_id The ID of the contact type
     *    - first_name The first name on the contact
     *    - last_name The last name on the contact
     *    - title The title of the contact
     *    - company The company name of the contact
     *    - address1 The address 1 line of the contact
     *    - address2 The address 2 line of the contact
     *    - city The city of the contact
     *    - state An array of state info including:
     *        - code The 2 or 3-character state code
     *        - name The local name of the country
     *    - country An array of country info including:
     *        - alpha2 The 2-character country code
     *        - alpha3 The 3-character country code
     *        - name The english name of the country
     *        - alt_name The local name of the country
     *    - zip The zip/postal code of the contact
     * @param float $amount The amount to charge this contact
     * @param array $invoice_amounts An array of invoices, each containing:
     *    - id The ID of the invoice being processed
     *    - amount The amount being processed for this invoice (which is included in $amount)
     * @param array $options An array of options including:
     *    - description The Description of the charge
     *    - return_url The URL to redirect users to after a successful payment
     *    - recur An array of recurring info including:
     *        - amount The amount to recur
     *        - term The term to recur
     *        - period The recurring period (day, week, month, year, onetime) used in conjunction with term in order to determine the next recurring payment
     * @return string HTML markup required to render an authorization and capture payment form
     */
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null)
    {
        $post_to = "";
        $fields = array();
        $contact = false;

        $this->view = $this->makeView("process", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));
        $this->initPaymentwallConfigs();

        // Load the helpers required for this view
        Loader::loadHelpers($this, array("Form", "Html"));

        // Set contact email address and phone number
        if ($this->ifSet($contact_info['id'], false)) {
            Loader::loadModels($this, array("Contacts"));
            $contact = $this->Contacts->get($contact_info['id']);
        } else {
            return "Contact information invalid!";
        }

        // Create widget
        $widget = new Paymentwall_Widget(
            $contact->client_id,
            $this->meta['widget_code'],
            array(
                new Paymentwall_Product(
                    $this->generateProductId($invoice_amounts, $contact, $amount, $this->getCurrentGatewayId(), Configure::get("Blesta.company_id")),
                    $amount,
                    $this->currency,
                    $options['description']
                )
            ),
            array_merge(
                array(
                    'email' => $contact->email,
                    'integration_module' => 'blesta',
                    'success_url' => $options['return_url'],
                    'test_mode' => $this->meta['test_mode'] == 'true' ? 1 : 0,
                    'invoice' => $this->serializeInvoices($invoice_amounts),
                    'callback_url' => Configure::get("Blesta.gw_callback_url") . Configure::get("Blesta.company_id") . '/paymentwall',
                ),
                $this->prepareUserProfileData($contact)
            )
        );

        $this->view->set("widget", $widget);
        $this->view->set("post_to", $post_to);
        $this->view->set("fields", $fields);

        return $this->view->fetch();
    }

    /**
     * @param $invoice_amounts
     * @param $contact
     * @param $amount
     * @param $gateway_id
     * @param $company_id
     * @return string
     */
    private function generateProductId($invoice_amounts, $contact, $amount, $gateway_id, $company_id)
    {
        return implode('|', array(
            empty($invoice_amounts) ? self::PW_PAYMENT_CREDIT : self::PW_PAYMENT_NORMAL,
            $contact->client_id,
            $amount,
            $this->currency,
            $gateway_id,
            $company_id
        ));
    }

    /**
     * @param $contact
     * @return array
     */
    private function prepareUserProfileData($contact)
    {
        return array(
            'customer[city]' => $contact->city,
            'customer[state]' => $contact->state,
            'customer[address]' => $contact->address1,
            'customer[country]' => $contact->country,
            'customer[zip]' => $contact->zip,
            'customer[username]' => $contact->email,
            'customer[firstname]' => $contact->first_name,
            'customer[lastname]' => $contact->last_name,
        );
    }

    /**
     * Process Pingback Request
     *
     * Validates the incoming POST/GET response from the gateway to ensure it is
     * legitimate and can be trusted.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, sets any errors using Input if the data fails to validate
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *    - id The ID of the invoice to apply to
     *    - amount The amount to apply to the invoice
     *    - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *    - reference_id The reference ID for gateway-only use with this transaction (optional)
     *    - transaction_id The ID returned by the gateway to identify this transaction
     *    - parent_transaction_id The ID returned by the gateway to identify this transaction's original transaction (in the case of refunds)
     */
    public function validate(array $get, array $post)
    {
        $status = "error";
        $amount = 0;
        $currency = '';

        $this->initPaymentwallConfigs();
        $pingback = new Paymentwall_Pingback($_GET, $_SERVER['REMOTE_ADDR']);

        list($type, $client_id, $amount, $currency, $gateway_id, $company_id) = explode('|', $pingback->getProductId());
        if (!$amount OR !$currency) {
            $this->Input->setErrors($this->getCommonError("invalid"));
        }

        if ($pingback->validate()) {
            if ($pingback->isDeliverable()) {
                $status = 'approved';
            } elseif ($pingback->isCancelable()) {
                $status = 'declined';
            }

        } else {
            $status = 'error';
        }

        // Log the response
        $this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($get), "output", true);

        // Clone function processNotification in class GatewayPayments
        // Process transaction after validate
        $this->processTransaction(array(
            'client_id' => $pingback->getUserId(),
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
            'reference_id' => null,
            'transaction_id' => $pingback->getReferenceId(),
            'parent_transaction_id' => null,
            'invoices' => $this->unserializeInvoices($pingback->getParameter('invoice')), // optional
            'gateway_id' => $gateway_id,
            'company_id' => $company_id
        ));
    }

    /**
     * Returns data regarding a success transaction. This method is invoked when
     * a client returns from the non-merchant gateway's web site back to Blesta.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, may set errors using Input if the data appears invalid
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *    - id The ID of the invoice to apply to
     *    - amount The amount to apply to the invoice
     *    - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *    - transaction_id The ID returned by the gateway to identify this transaction
     *    - parent_transaction_id The ID returned by the gateway to identify this transaction's original transaction
     */
    public function success(array $get, array $post)
    {
        return array(
            'client_id' => $this->ifSet($post['client_id']),
            'amount' => $this->ifSet($post['total']),
            'currency' => $this->ifSet($post['currency_code']),
            'invoices' => unserialize(base64_decode($this->ifSet($post['invoices']))),
            'status' => "approved",
            'transaction_id' => $this->ifSet($post['order_number']),
            'parent_transaction_id' => null
        );
    }

    /**
     * Refund a payment
     *
     * @param string $reference_id The reference ID for the previously submitted transaction
     * @param string $transaction_id The transaction ID for the previously submitted transaction
     * @param float $amount The amount to refund this card
     * @param string $notes Notes about the refund that may be sent to the client by the gateway
     * @return array An array of transaction data including:
     *    - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *    - reference_id The reference ID for gateway-only use with this transaction (optional)
     *    - transaction_id The ID returned by the remote gateway to identify this transaction
     *    - message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
     */
    public function refund($reference_id, $transaction_id, $amount, $notes = null)
    {
        $this->Input->setErrors($this->getCommonError("unsupported"));
    }

    /**
     * Captures a previously authorized payment
     *
     * @param string $reference_id The reference ID for the previously authorized transaction
     * @param string $transaction_id The transaction ID for the previously authorized transaction
     * @return array An array of transaction data including:
     *    - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *    - reference_id The reference ID for gateway-only use with this transaction (optional)
     *    - transaction_id The ID returned by the remote gateway to identify this transaction
     *    - message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
     */
    public function capture($reference_id, $transaction_id, $amount, array $invoice_amounts = null)
    {
        $this->Input->setErrors($this->getCommonError("unsupported"));
    }

    /**
     * @param array $invoices
     * @return string
     */
    private function serializeInvoices(array $invoices)
    {
        $str = "";
        foreach ($invoices as $i => $invoice)
            $str .= ($i > 0 ? "|" : "") . $invoice['id'] . "=" . $invoice['amount'];
        return $str;
    }

    /**
     * @param $str
     * @return array
     */
    private function unserializeInvoices($str)
    {
        $invoices = array();
        if ($str) {
            $temp = explode("|", $str);
            foreach ($temp as $pair) {
                list($id, $amount) = explode("=", $pair, 2);
                $invoices[] = array('id' => $id, 'amount' => $amount);
            }
        }
        return $invoices;
    }

    /**
     * @return mixed|null
     */
    protected function getCurrentGatewayId()
    {
        if (!$this->gwId) {
            $r = new Record();
            $row = $r->select("id")->from("gateways")
                ->where("class", "=", 'paymentwall')
                ->where("company_id", "=", Configure::get("Blesta.company_id"))->fetch(PDO::FETCH_ASSOC);
            $this->gwId = $row ? reset($row) : null;
        }

        return $this->gwId;
    }

    /**
     * @param $id
     */
    protected function setCurrentGatewayId($id)
    {
        $this->gwId = $id;
    }

    /**
     * @param $response
     */
    private function processTransaction($response)
    {
        $transaction_id = '';
        // If a response was given, record the transaction
        if (is_array($response)) {

            Loader::loadModels($this, array('Transactions', 'Clients', 'Companies', 'Emails'));
            Loader::loadHelpers($this, array('Date', 'CurrencyFormat'));

            $trans_data = array(
                'client_id' => $response['client_id'],
                'amount' => $response['amount'],
                'currency' => $response['currency'],
                'type' => "other",
                'gateway_id' => $response['gateway_id'],
                'transaction_id' => isset($response['transaction_id']) ? $response['transaction_id'] : null,
                'reference_id' => isset($response['reference_id']) ? $response['reference_id'] : null,
                'parent_transaction_id' => isset($response['parent_transaction_id']) ? $response['parent_transaction_id'] : null,
                'status' => $response['status']
            );


            // If the transaction exists, update it
            if ($trans_data['transaction_id'] && $transaction = $this->Transactions->getByTransactionId($trans_data['transaction_id'], null, $response['gateway_id'])) {
                // Don't update client_id to prevent transaction from being reassigned
                unset($trans_data['client_id']);

                $this->Transactions->edit($transaction->id, $trans_data);
                $transaction_id = $transaction->id;
            } // Add the transaction
            else {
                $transaction_id = $this->Transactions->add($trans_data);
            }
        } // If no response given and errors set, pass those errors along
        elseif (($errors = $this->errors())) {
            $this->Input->setErrors($errors);
            die('Transaction invalid!');
        }

        // Set any errors with adding the transaction
        if (($errors = $this->Transactions->errors())) {
            $this->Input->setErrors($errors);
            die('Cannot process transaction #' . $transaction_id);
        } else {

            // Apply the transaction to the invoices given (if any)
            if (isset($response['invoices']) && is_array($response['invoices'])) {
                // Format invoices into something suitable for Transactions::apply()
                foreach ($response['invoices'] as &$invoice) {
                    $invoice['invoice_id'] = $invoice['id'];
                    unset($invoice['id']);
                }

                if (!empty($response['invoices']) && $response['status'] == "approved")
                    $this->Transactions->apply($transaction_id, array('amounts' => $response['invoices']));
            }

            $transaction = $this->Transactions->get($transaction_id);

            // Send an email regarding the non-merchant payment received
            if (isset($response['status']) && isset($response['client_id']) &&
                $response['status'] == "approved" && $transaction &&
                ($client = $this->Clients->get($response['client_id']))
            ) {

                // Set date helper info
                $this->Date->setTimezone("UTC", Configure::get("Blesta.company_timezone"));
                $this->Date->setFormats(array(
                    'date_time' => $this->Companies->getSetting($response['company_id'], "datetime_format")->value
                ));

                $amount = $this->CurrencyFormat->format($transaction->amount, $transaction->currency);

                $tags = array(
                    'contact' => $client,
                    'transaction' => $transaction,
                    'date_added' => $this->Date->cast($transaction->date_added, "date_time")
                );

                $this->Emails->send("payment_nonmerchant_approved", $response['company_id'], $client->settings['language'], $client->email, $tags, null, null, null, array('to_client_id' => $client->id));
            }

            die('OK');
        }
    }
}
