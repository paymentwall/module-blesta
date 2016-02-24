<?php

if (!class_exists('Paymentwall_Config'))
    include(getcwd() . '/components/gateways/lib/paymentwall-php/lib/paymentwall.php');

/**
 * Paymentwall
 *
 * @copyright Copyright (c) 2015, Paymentwall, Inc.
 * @link http://www.paymentwall.com/ Paymentwall
 */
class Brick extends NonmerchantGateway
{
    /**
     * @var string The version of this Gateway
     */
    private static $version = "1.0.0";
    /**
     * @var string The authors of this Gateway
     */
    private static $authors = array(array('name' => "Paymentwall, Inc.", 'url' => "https://www.paymentwall.com"));
    /**
     * @var array An array of meta data for this Gateway
     */
    private $meta;

    /**
     * Construct a new merchant Gateway
     */
    public function __construct()
    {
        // Load components required by this module
        Loader::loadComponents($this, array("Input"));

        // Load the language required by this module
        Language::loadLang("brick", null, dirname(__FILE__) . DS . "language" . DS);
    }

    /**
     * Initial Paymentwall settings
     */
    public function initPaymentwallConfigs()
    {
        Paymentwall_Config::getInstance()->set(array(
            'public_key' => $this->meta['test_mode'] ? $this->meta['public_test_key'] : $this->meta['public_key'],
            'private_key' => $this->meta['test_mode'] ? $this->meta['private_test_key'] : $this->meta['private_key']
        ));
    }

    /**
     * Attempt to install this Gateway
     */
    public function install()
    {
        // Ensure that the system has support for the JSON extension
        if (!function_exists("json_decode")) {
            $errors = array(
                'json' => array(
                    'required' => Language::_("Brick.!error.json_required", true)
                )
            );
            $this->Input->setErrors($errors);
        }
    }

    /**
     * Returns the name of this Gateway
     *
     * @return string The common name of this Gateway
     */
    public function getName()
    {
        return Language::_("Brick.name", true);
    }

    /**
     * Returns the version of this Gateway
     *
     * @return string The current version of this Gateway
     */
    public function getVersion()
    {
        return self::$version;
    }

    /**
     * Returns the name and URL for the authors of this Gateway
     *
     * @return array The name and URL of the authors of this Gateway
     */
    public function getAuthors()
    {
        return self::$authors;
    }

    /**
     * Return all currencies supported by this Gateway
     *
     * @return array A numerically indexed array containing all currency codes (ISO 4217 format) this Gateway supports
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
     * Validates the given meta (settings) data to be updated for this Gateway
     *
     * @param array $meta An array of meta (settings) data to be updated for this Gateway
     * @return array The meta data to be updated in the database for this Gateway, or reset into the form on failure
     */
    public function editSettings(array $meta)
    {
        // Verify meta data is valid
        $rules = array(
            'public_key' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("Brick.!error.public_key.valid", true),
                    'post_format' => 'trim'
                )
            ),
            'private_key' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("Brick.!error.private_key.valid", true),
                    'post_format' => 'trim'
                )
            ),
            'test_mode' => array(
                'valid' => array(
                    'if_set' => true,
                    'rule' => array("in_array", array("true", "false")),
                    'message' => Language::_("Brick.!error.test_mode.valid", true)
                )
            )
        );

        // Set checkbox if not set
        if (!isset($meta['test_mode']))
            $meta['test_mode'] = "false";

        $this->Input->setRules($rules);

        // Validate the given meta data to ensure it meets the requirements
        $this->Input->validates($meta);
        // Return the meta data, no changes required regardless of success or failure for this Gateway
        return $meta;
    }

    /**
     * Returns an array of all fields to encrypt when storing in the database
     *
     * @return array An array of the field names to encrypt when storing in the database
     */
    public function encryptableFields()
    {
        return array(
            "public_key",
            "private_key",
            "public_test_key",
            "private_test_key",
        );
    }

    /**
     * Sets the meta data for this particular Gateway
     *
     * @param array $meta An array of meta data to set for this Gateway
     */
    public function setMeta(array $meta = null)
    {
        $this->meta = $meta;
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

        if(!isset($get['data'])) return false;

        $data = $this->decodeData($get['data']);
        unset($get['data']);

        list($company_id, $payment_method) = $get;
        if ($payment_method != 'brick') return false;

        $brick = $post['brick'];

        $this->initPaymentwallConfigs();

        $status = 'error';
        $cardInfo = array(
            'email' => $data['email'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'description' => $data['description'],
            'token' => $brick['token'],
            'fingerprint' => $brick['fingerprint'],
        );

        $charge = new Paymentwall_Charge();
        $charge->create($cardInfo);
        $response = json_decode($charge->getPublicData(), true);

        if ($charge->isSuccessful()) {
            if ($charge->isCaptured()) {
                // deliver a product
                $status = 'approved';
            } elseif ($charge->isUnderReview()) {
                // decide on risk charge
                $status = 'pending';
            }
        } else {
            $_SESSION['brick_errors'] = $response['error']['message'];
        }

        return array(
            'client_id' => $data['client_id'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'status' => $status,
            'reference_id' => null,
            'transaction_id' => $charge->getId() ? $charge->getId() : false,
            'parent_transaction_id' => null,
            'invoices' => $data['invoices'] // optional
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
        if(isset($_SESSION['brick_errors']) && $_SESSION['brick_errors']){
            $this->Input->setErrors(array(
                array(
                    'general' => $_SESSION['brick_errors']
                )
            ));
            unset($_SESSION['brick_errors']);
        }

        return array(
            'client_id' => $this->ifSet($post['client_id']),
            'amount' => $this->ifSet($post['total']),
            'currency' => $this->ifSet($post['currency_code']),
            'invoices' => null,
            'status' => "approved",
            'transaction_id' => $this->ifSet($post['order_number']),
            'parent_transaction_id' => null
        );
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
        $this->initPaymentwallConfigs();

        $post_to = Configure::get("Blesta.gw_callback_url") . Configure::get("Blesta.company_id") . "/brick/";
        $fields = array();
        $contact = false;

        // Set contact email address and phone number
        if ($this->ifSet($contact_info['id'], false)) {
            Loader::loadModels($this, array("Contacts"));
            $contact = $this->Contacts->get($contact_info['id']);
        } else {
            return "Contact information invalid!";
        }

        $data = array(
            'public_key' => Paymentwall_Config::getInstance()->getPublicKey(),
            'amount' => $amount,
            'merchant' => $this->ifSet($this->meta['merchant_name'], 'Blesta'),
            'product_name' => $options['description'],
            'currency' => $this->currency
        );

        $post_to .= "?data=" . $this->encodeData(array(
                'client_id' => $contact->client_id,
                'amount' => $amount,
                'currency' => $this->currency,
                'invoices' => $invoice_amounts,
                'email' => $contact->email,
                'description' => $options['description'],
            ));

        $this->view = $this->makeView("process", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));
        $this->view->set("data", $data);
        $this->view->set("post_to", $post_to);
        $this->view->set("fields", $fields);

        return $this->view->fetch();
    }

    /**
     * @param array $data
     * @return string
     */
    private function encodeData($data = array())
    {
        return base64_encode(serialize($data));
    }

    /**
     * @param $strData
     * @return mixed
     */
    private function decodeData($strData)
    {
        return unserialize(base64_decode($strData));
    }
}
