<?php

if(!class_exists('Paymentwall_Config'))
    include(getcwd() . '/components/Gateways/lib/paymentwall-php/lib/paymentwall.php');

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
            'enable_delivery' => array(
                'valid' => array(
                    'rule' => array("in_array", array('true', 'false')),
                    'message' => Language::_("Paymentwall.!error.enable_delivery.valid", true)
                )
            ),
            'test_mode' => array(
                'valid' => array(
                    'rule' => array("in_array", array('true', 'false')),
                    'message' => Language::_("Paymentwall.!error.test_mode.valid", true)
                )
            ),
        );

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
                    $this->generateProductId($invoice_amounts, $contact, $amount),
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
                    'test_mode' => $this->meta['test_mode'] ? 1 : 0,
                    'invoice' => $this->serializeInvoices($invoice_amounts)
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
     * @return string
     */
    private function generateProductId($invoice_amounts, $contact, $amount)
    {
        return implode('|', array(
            empty($invoice_amounts) ? self::PW_PAYMENT_CREDIT : self::PW_PAYMENT_NORMAL,
            $contact->client_id,
            $amount,
            $this->currency
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
        if ($get[0] != 'paymentwall') {
            return false;
        }

        $status = "error";
        $amount = 0;
        $currency = '';

        $this->initPaymentwallConfigs();
        $pingback = new Paymentwall_Pingback($_GET, $_SERVER['REMOTE_ADDR']);

        list($type, $client_id, $amount, $currency) = explode('|', $pingback->getProductId());
        if (!$amount OR !$currency) {
            $this->Input->setErrors($this->getCommonError("invalid"));
        }

        if ($pingback->validate()) {

            $status = 'approved';
            if ($pingback->isDeliverable()) {

            } elseif ($pingback->isCancelable()) {
                $status = 'declined';
            }

        } else {
            $status = 'error';
        }

        // Log the response
        $this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($get), "output", true);

        return array(
            'client_id' => $pingback->getUserId(),
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
            'reference_id' => null,
            'transaction_id' => $pingback->getReferenceId(),
            'parent_transaction_id' => null,
            'invoices' => $this->unserializeInvoices($pingback->getParameter('invoice')) // optional
        );
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
        $temp = explode("|", $str);
        foreach ($temp as $pair) {
            list($id, $amount) = explode("=", $pair, 2);
            $invoices[] = array('id' => $id, 'amount' => $amount);
        }
        return $invoices;
    }
}
