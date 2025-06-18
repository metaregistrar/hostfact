<?php
require_once("3rdparty/domain/IRegistrar.php");
require_once("3rdparty/domain/standardfunctions.php");
require_once("3rdparty/domain/metaregistrar/mtr.php");
/**
 * -------------------------------------------------------------------------------------
 * Metaregistrar - IRegistrar
 *
 * Author		: Ewout de Graaf
 * Copyright	: (c) 2018 Metaregistrar BV
 * Version 		: 1.2
 *
 * CHANGE LOG:
 * -------------------------------------------------------------------------------------
 *  2017-08-18		E.W. de Graaf 		Initial version
 *  2017-08-23      E.W. de Graaf       Added DNS management
 *  2025-06-01      E.W. de Graaf       Small fixes, up-to-date with php 8.x
 *  2025-06-01      E.W. de Graaf       Fixed proper syncing of auto-renew flag for domain name
 *  2025-06-17      E.W. de Graaf       Major improvements in error handling
 * -------------------------------------------------------------------------------------
 */

class metaregistrar implements IRegistrar
{
    public $User;
    public $Password;
    public $Testmode;

    public $Error = [];
    public $Warning = [];
    public $Success = [];

    public $Period = 1;
    public $registrarHandles = array();

    private $ClassName;
    /* @var $mtr mtr() */
    private $mtr = null;

    /**
     * metaregistrar constructor.
     */
    function __construct() {
        $this->ClassName = __CLASS__;
        //$this->Error = array();
        //$this->Warning = array();
        //$this->Success = array();
        return true;
    }

    /**
     * metaregistrar destructor
     */
    function __destruct() {
        return true;
    }

    /*
     *
     * STANDARD Wefact functions
     * These functions come with the standard Wefact implemenation and call the metaregistrar functions where applicable
     */

    /**
     * Check whether a domain is already registered or not.
     *
     * @param 	string	 $domain	The name of the domain that needs to be checked.
     * @return 	boolean 			True if free, False if not free, False and $this->Error[] in case of error.
     */
    function checkDomain($domain) {
        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        $result = $this->mtr->checkdomain($domain);
        if (!$result) {
            $this->Error[] = $this->mtr->getLastError();
            return false;
        }
        return $result;
    }


    /**
     * Register a new domain
     *
     * @param 	string	$domain			The domainname that needs to be registered.
     * @param 	array	$nameservers	The nameservers for the new domain.
     * @param 	array	$whois			The customer information for the domain's whois information.
     * @return 	bool					True on success; False otherwise.
     */
    function registerDomain($domain, $nameservers = array(), $whois = null) {
        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        /** if you use DNS management, the following variables are also available
         * $this->DNSTemplateID
         * $this->DNSTemplateName
         */
        $ownerHandle = "";
        // Check if a registrar-specific ownerhandle for this domain already exists.
        if (isset($whois->ownerRegistrarHandles[$this->ClassName])) {
            $ownerHandle = $whois->ownerRegistrarHandles[$this->ClassName];
        }
        // If not, check if WHOIS-data for owner contact is available to search or create new handle
        elseif($whois->ownerSurName != "") {
            // Search for existing handle, based on WHOIS data (not supported by the Metaregistrar API)
            $ownerHandle = $this->getContactHandle($whois, HANDLE_OWNER);
            // If no existing handle is found, create new handle
            if ($ownerHandle == "") {
                // Try to create new handle. In case of failure, quit function
                if(!$ownerHandle = $this->createContact($whois, HANDLE_OWNER)) {
                    if ($this->ErrorsOccurred()) return false;
                }
            }

            // If a new handle is created or found, store in array. WeFact will store this data, which will result in faster registration next time.
            $this->registrarHandles['owner'] = $ownerHandle;
        } else {
            // If no handle can be created, because data is missing, quit function
            $this->Error[] = sprintf("Metaregistrar: Geen eigenaar ingesteld voor domeinnaam '%s'.", $domain);
            return false;
        }

        $adminHandle = $ownerHandle;

        $techHandle = $ownerHandle;

        // If your system uses nameserver groups or handles, you can check the $nameserver array and match these hostnames with your own system.
        $requestns = [];
        foreach ($nameservers as $index=>$ns) {
            // Filter out IP addresses
            if (strpos($index,'ip')===false) {
                $requestns[] = $ns;
            }
        }
        // Determine period for registration in years, based on your TLD properties.
        // $this->Period is also used in WeFact, for determining the renewal date.
        $this->Period = 1;

        // Start registering the domain, you can use $domain, $ownerHandle, $adminHandle, $techHandle, $nameservers
        $response = $this->mtr->createdomain($domain, $ownerHandle, $adminHandle, $techHandle, $requestns, $this->Period, null);
        if (!$response) {
            $this->Error[] = $this->mtr->getLastError();
            return false;
        }
        return $response;
    }

    /**
     * Transfer a domain to the given user.
     *
     * @param 	string 	$domain			The demainname that needs to be transfered.
     * @param 	array	$nameservers	The nameservers for the tranfered domain.
     * @param 	array	$whois			The contact information for the new owner, admin, tech and billing contact.
     * @return 	bool					True on success; False otherwise;
     */
    function transferDomain($domain, $nameservers = array(), $whois = null, $authcode = "") {
        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        /** if you use DNS management, the following variables are also available
         * $this->DNSTemplateID
         * $this->DNSTemplateName
         */

        $ownerHandle = "";
        // Check if a registrar-specific ownerhandle for this domain already exists.
        if (isset($whois->ownerRegistrarHandles[$this->ClassName]))
        {
            $ownerHandle = $whois->ownerRegistrarHandles[$this->ClassName];
        }
        // If not, check if WHOIS-data for owner contact is available to search or create new handle
        elseif($whois->ownerSurName != "")
        {
            // Search for existing handle, based on WHOIS data (not supported by the Metaregistrar API)
            //$ownerHandle = $this->getContactHandle($whois, HANDLE_OWNER);

            // If no existing handle is found, create new handle
            if ($ownerHandle == "")
            {
                // Try to create new handle. In case of failure, quit function
                if(!$ownerHandle = $this->createContact($whois, HANDLE_OWNER))
                {
                    return false;
                }
            }

            // If a new handle is created or found, store in array. WeFact will store this data, which will result in faster transfer next time.
            $this->registrarHandles['owner'] = $ownerHandle;
        } else {
            // If no handle can be created, because data is missing, quit function
            $this->Error[] = sprintf("Metaregistrar: Geen eigenaar opgegeven voor domeinnaam '%s'.", $domain);
            return false;
        }

        $adminHandle = "";
        // Check if a registrar-specific adminhandle for this domain already exists.
        if (isset($whois->adminRegistrarHandles[$this->ClassName]))
        {
            $adminHandle = $whois->adminRegistrarHandles[$this->ClassName];
        }
        // If not, check if WHOIS-data for admin contact is available to search or create new handle
        elseif($whois->adminSurName != "")
        {
            // Search for existing handle, based on WHOIS data (not supported by the Metaregistrar API)
            //$adminHandle = $this->getContactHandle($whois, HANDLE_ADMIN);

            // If no existing handle is found, create new handle
            if ($adminHandle == "")
            {
                // Try to create new handle. In case of failure, quit function
                if(!$adminHandle = $this->createContact($whois, HANDLE_ADMIN))
                {
                    return false;
                }
            }

            // If a new handle is created or found, store in array. WeFact will store this data, which will result in faster transfer next time.
            $this->registrarHandles['admin'] = $adminHandle;
        } else {
            // If no handle can be created, because data is missing, quit function
            $this->Error[] = sprintf("Metaregistrar: Geen admin-c contact opgegeven voor domeinnaam '%s'.", $domain);
            return false;
        }

        $techHandle = "";
        // Check if a registrar-specific techhandle for this domain already exists.
        if (isset($whois->techRegistrarHandles[$this->ClassName]))
        {
            $techHandle = $whois->techRegistrarHandles[$this->ClassName];
        }
        // If not, check if WHOIS-data for tech contact is available to search or create new handle
        elseif($whois->techSurName != "")
        {
            // Search for existing handle, based on WHOIS data (not supported by the Metaregistrar API)
            //$techHandle = $this->getContactHandle($whois, HANDLE_TECH);

            // If no existing handle is found, create new handle
            if ($techHandle == "")
            {
                // Try to create new handle. In case of failure, quit function
                if(!$techHandle = $this->createContact($whois, HANDLE_TECH))
                {
                    return false;
                }
            }

            // If a new handle is created or found, store in array. WeFact will store this data, which will result in faster transfer next time.
            $this->registrarHandles['tech'] = $techHandle;
        } else {
            // If no handle can be created, because data is missing, quit function
            $this->Error[] = sprintf("Metaregistrar: Geen tech-c contact opgegeven voor domeinnaam '%s'.", $domain);
            return false;
        }

        // If your system uses nameserver groups or handles, you can check the $nameserver array and match these hostnames with your own system.

        // Determine period for transfer in years, based on your TLD properties.
        // $this->Period is also used in WeFact, for determining the renewal date.
        $this->Period = 1;

        // Start transferring the domain, you can use $domain, $ownerHandle, $adminHandle, $techHandle, $nameservers, $authcode
        $response = $this->mtr->transferdomain($domain, $ownerHandle, $adminHandle, $techHandle, $nameservers, $this->Period, $authcode);
        if (!$response) {
            $this->Error[] = $this->mtr->getLastError();
            return false;
        }
        return $response;
    }

    /**
     * Delete a domain
     *
     * @param 	string $domain		The name of the domain that you want to delete.
     * @param 	string $delType     end|now
     * @return	bool				True if the domain was succesfully removed; False otherwise;
     */
    function deleteDomain($domain, $delType = 'end') {
        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        if ($delType == "end"){
            return $this->setDomainAutoRenew($domain, false);
        } else {
            // Delete the domain, you can use $domain
            $response = $this->mtr->deletedomain($domain);
            if (!$response) {
                $this->Error[] = $this->mtr->getLastError();
                return false;
            }
        }
    }


    /**
     * Get all available information of the given domain
     *
     * @param 	mixed 	$domain		The domain for which the information is requested.
     * @return	array|bool			The array containing all information about the given domain
     */
    function getDomainInformation($domain) {
        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        if($info= $this->mtr->getdomaininfo($domain)) {
            $whois = new whois();
            $whois->ownerHandle = $info['registrant'];
            $whois->adminHandle = $info['admin-c'];
            $whois->techHandle 	= $info['tech-c'];

            // Return array with data
            $response = array(	"Domain" => $domain,
                "Information" => array(	"nameservers" => $info['nameservers'],
                    "whois" => $whois, // Whois object
                    "expiration_date" => $info['expdate'],
                    "registration_date" => $info['regdate'],
                    "auto_renew" => ($info['autorenew'] ? 'on' : 'off'),
                    "authkey" => $info['authcode']));
            return $response;
        }
        else {
            // No information can be found
            $this->Error[] = $this->mtr->getLastError();
            return false;

        }
    }

    /**
     * Get a list of all the domains.
     *
     * @param 	string 	$contactHandle		The handle of a contact, so the list could be filtered (usefull for updating domain whois data)
     * @return	bool|array					A list of all domains available in the system.
     */
    function getDomainList($contactHandle = "") {
        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        if (!is_file(dirname(__FILE__).'/import/domeinnamen.csv')) {
            $this->Error[] = "File 'domeinnamen.csv' niet gevonden. Plaats een bestand met domeinnamen in de map 'import' en noem dit bestand 'domeinnamen.csv'";
            return false;
        }
        /**
         * Step 1) query domain
         */
        // Query the domain, you can use $domain
        $response 	= true;
        $error_msg 	= '';
        $domains = file(dirname(__FILE__).'/import/domeinnamen.csv',FILE_IGNORE_NEW_LINES);
        set_time_limit(1000);

        /**
         * Step 2) provide feedback to WeFact
         */
        if ((is_array($domains)) && (count($domains)>0))
        {
            $domain_array = array();

            // Loop for all domains:
            foreach ($domains as $domain) {
                $info = $this->mtr->getdomaininfo($domain);
                if ($info) {
                    $whois = new whois();
                    $whois->ownerHandle = $info['registrant'];
                    $whois->adminHandle = $info['admin-c'];
                    $whois->techHandle 	= $info['tech-c'];

                    // Return array with data
                    $response = array(	"Domain" => $domain,
                        "Information" => array(	"nameservers" => $info['nameservers'], // Array with 1, 2 or 3 elements (hostnames)
                            "whois" => $whois, // Whois object
                            "expiration_date" => $info['expdate'],  // Empty or date in yyyy-mm-dd
                            "registration_date" => $info['credate'],  // Empty or date in yyyy-mm-dd
                            "authkey" => $info['authinfo']));
                    $domain_array[] = $response;
                } else {
                    $this->Error[] = $this->mtr->getLastError();
                    return false;
                }
            }

            // When loop is ready, return array
            return $domain_array;
        }
        else
        {
            // No domains can be found
            $this->Error[] 	= sprintf("Metaregistrar: Lijst met domeinnamen niet gevonden of leeg: %s", $error_msg);
            return false;
        }
    }

    /**
     * Change the lock status of the specified domain.
     *
     * @param 	string 	$domain		The domain to change the lock state for
     * @param 	bool 	$lock		The new lock state (True|False)
     * @return	bool				True is the lock state was changed succesfully
     */
    function lockDomain($domain, $lock = true) {
        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        $response = $this->mtr->lockdomain($domain, $lock);
        if (!$response) {
            $this->Error[] = $this->mtr->getLastError();
            return false;
        }
        return $response;
    }

    /**
     * Change the autorenew state of the given domain. When autorenew is enabled, the domain will be extended.
     *
     * @param 	string	$domain			The domainname to change the autorenew setting for,
     * @param 	bool	$autorenew		The new autorenew setting (True = On|False = Off)
     * @return	bool					True when the setting is succesfully changed; False otherwise
     */
    function setDomainAutoRenew($domain, $autorenew = true) {
        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        $response = $this->mtr->updateautorenew($domain, $autorenew);
        if (!$response) {
            $this->Error[] = $this->mtr->getLastError();
            return false;
        }
        return $response;
    }

    /**
     * Get EPP code/token
     *
     * @param mixed $domain
     * @return
     */
    public function getToken($domain){
        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        $response 	= $this->mtr->getdomaininfo($domain);
        if (!$response) {
            $this->Error[] = $this->mtr->getLastError();
            return false;
        }

        // EPP code is retrieved
        return $response['authcode'];

    }

    /**
     * getSyncData()
     * Check domain information for one or more domains
     * @param mixed $list_domains	Array with list of domains. Key is domain, value must be filled.
     * @return mixed $list_domains
     */
    public function getSyncData($list_domains) {

        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        /**
         * There are two scenario's for retrieving the information
         * 1. Get the domain list which provide sufficient information for this function
         * 2. We must request the info per domain. Take care of script timeout
         *
         * Both scenario's can be found below
         */

        /**
         * Scenario 2: We must request the info per domain. Take care of script timeout
         */
        $max_domains_to_check = 100;
        $checked_domains = 0;
        // Check domain one for one
        foreach($list_domains as $domain_name => $value) {
            // Ask registrar for information of domain
            $info = $this->mtr->getdomaininfo($domain_name);
            if (!$info) {
                $list_domains[$domain_name]['Status']    = 'error';
                $list_domains[$domain_name]['Error_msg'] = $this->mtr->getLastError();
                continue;
            }
            // Add data
            $nameservers_array  = $info['nameservers'];
            $expirationdate     = date('Y-m-d',strtotime($info['expdate']));
            $registrationdate   = date('Y-m-d',strtotime($info['regdate']));
            $auto_renew         = $info['autorenew'];

            // extend the list_domains array with data from the registrar
            $list_domains[$domain_name]['Information']['nameservers']        = $nameservers_array;
            $list_domains[$domain_name]['Information']['expiration_date']    = (isset($expirationdate)) ? $expirationdate : '';
            $list_domains[$domain_name]['Information']['registration_date']  = (isset($registrationdate)) ? $registrationdate : '';
            $list_domains[$domain_name]['Information']['auto_renew']         = ($auto_renew ? 'on' : 'off');
            $list_domains[$domain_name]['Status'] = 'success';

            // Increment counter
            $checked_domains++;
            // Stop loop after max domains
            if($checked_domains >= $max_domains_to_check) {
                break;
            }
        }
        // Return list  (domains which aren't completed with data, will be synced by a next cronjob)
        return $list_domains;
    }

    /**
     * Update the domain Whois data, but only if no handles are used by the registrar.
     *
     * @param mixed $domain
     * @param mixed $whois
     * @return boolean True if succesfull, false otherwise
     */
    function updateDomainWhois($domain, $whois){
        $this->Error[] = 'Contact bijwerken via WHOIS optie wordt niet ondersteund, bewerk het contact en vink "updaten bij registrar" aan om de whois gegevens van het contact bij te werken';
        return false;
    }

    /**
     * get domain whois handles
     *
     * @param mixed $domain
     * @return array|bool with handles
     */
    function getDomainWhois($domain) {
        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        $info = $this->mtr->getdomaininfo($domain);
        if ($info) {
            $contacts = [];
            $contacts['ownerHandle'] 	= $info['registrant'];
            $contacts['adminHandle'] 	= $info['admin-c'];
            $contacts['techHandle'] 	= $info['tech-c'];
            return $contacts;
        } else {
            $this->Error[] = $this->mtr->getLastError();
            return false;
        }
    }

    /**
     * Create a new whois contact
     *
     * @param 	array		 $whois		The whois information for the new contact.
     * @param 	mixed 	 	 $type		The contact type. This is only used to access the right data in the $whois object.
     * @return	bool					Handle when the new contact was created succesfully; False otherwise.
     */
    function createContact($whois, $type = HANDLE_OWNER) {
        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        // Determine which contact type should be found
        switch($type) {
            case HANDLE_OWNER:	$prefix = "owner"; 	break;
            case HANDLE_ADMIN:	$prefix = "admin"; 	break;
            case HANDLE_TECH:	$prefix = "tech"; 	break;
            default:			$prefix = ""; 		break;

        }

        // Some help-functions, to obtain more formatted data
        $whois->getParam($prefix,'StreetName');		// Not only Address, but also StreetName, StreetNumber and StreetNumberAddon are available after calling this function.
        $whois->getParam($prefix,'CountryCode');	// Phone and faxnumber are split. CountryCode, PhoneNumber and FaxNumber available. CountryCode contains for example '+31'. PhoneNumber contains number without leading zero e.g. '123456789'.

        // Create the contact
        $handle 	= $this->mtr->createcontact($whois);
        if ($handle) {
            return $handle;
        }
        else {
            $this->Error[] 	= $this->mtr->getLastError();
            return false;
        }
    }

    /**
     * Update the whois information for the given contact person.
     *
     * @param string $handle	The handle of the contact to be changed.
     * @param array $whois The new whois information for the given contact.
     * @param mixed $type The of contact. This is used to access the right fields in the whois array
     * @return
     */
    function updateContact($handle, $whois, $type = HANDLE_OWNER) {
        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        // Determine which contact type should be found
        switch($type) {
            case HANDLE_OWNER:	$prefix = "owner"; 	break;
            case HANDLE_ADMIN:	$prefix = "admin"; 	break;
            case HANDLE_TECH:	$prefix = "tech"; 	break;
            default:			$prefix = ""; 		break;

        }

        // Some help-functions, to obtain more formatted data
        $whois->getParam($prefix,'StreetName');		// Not only Address, but also StreetName, StreetNumber and StreetNumberAddon are available after calling this function.
        $whois->getParam($prefix,'CountryCode');	// Phone and faxnumber are split. CountryCode, PhoneNumber and FaxNumber available. CountryCode contains for example '+31'. PhoneNumber contains number without leading zero e.g. '123456789'.

        /**
         * Step 1) Update the contact, it can depend on the modified data which action you should take.
         */
        // Update the contact

        // Available variables, see example in Appendix A of documentation.
        // e.g.
        // $whois->ownerSurName			To obtain surname of domain owner
        // $whois->adminPhoneNumber   	To obtain phone number of admin contact data
        $response = $this->mtr->updatecontact($handle, $whois);
        if (!$response) {
            $this->Error[] = $this->mtr->getLastError();
            return false;
        }
        return $response;
    }

    /**
     * Get information availabe of the requested contact.
     *
     * @param string $handle The handle of the contact to request.
     * @return array Information available about the requested contact.
     */
    function getContact($handle) {
        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        /**
         * Step 1) Create the contact
         */
        // Create the contact
        $response 	= $this->mtr->getcontactinfo($handle);
        if (!$response) {
            $this->Error[] = $this->mtr->getLastError();
            return false;
        }
        $whois 		= new whois();

        /**
         * Step 2) provide feedback to WeFact
         */
        if($response) {
            // The contact is found
            $whois->ownerCompanyName 	= $response['company'];
            $whois->ownerInitials		= "";
            $whois->ownerSurName		= $response['name'];
            $whois->ownerAddress 		= $response['address'];
            $whois->ownerZipCode 		= $response['postcode'];
            $whois->ownerCity 			= $response['city'];
            $whois->ownerCountry 		= $response['countrycode'];
            $whois->ownerPhoneNumber	= $response['phone'];
            $whois->ownerFaxNumber 		= $response['fax'];
            $whois->ownerEmailAddress	= $response['email'];

            return $whois;
        }
        else
        {
            // Contact cannot be found
            $this->Error[] 	= sprintf("Metaregistrar: Fout bij opvragen contactinformatie van %s", $handle);
            return $whois;
        }
    }


    /**
     * Get the handle of a contact.
     *
     * @param array $whois The whois information of contact
     * @param string $type The type of person. This is used to access the right fields in the whois object.
     * @return string handle of the requested contact; False if the contact could not be found.
     */
    function getContactHandle($whois = array(), $type = HANDLE_OWNER) {
        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        // Function to search contact by whois data is not supported
        $this->Error[] = 'Zoeken van contactinformatie via de whois wordt niet ondersteund door de Metaregistrar API';
        return false;

        // Determine which contact type should be found
        switch($type) {
            case HANDLE_OWNER:  $prefix = "owner";  break;
            case HANDLE_ADMIN:  $prefix = "admin";  break;
            case HANDLE_TECH:   $prefix = "tech";   break;
            default:            $prefix = "";       break;
        }

        /**
         * Step 1) Search for contact data
         */
        // Search for a handle which can be used
        $handle 	= '';

        /**
         * Step 2) provide feedback to WeFact
         */
        if($handle)
        {
            // A handle is found
            return $handle;
        }
        else
        {
            // No handle is found
            return false;
        }
    }

    /**
     * Get a list of contact handles available
     *
     * @param string $surname Surname to limit the number of records in the list.
     * @return array List of all contact matching the $surname search criteria.
     */
    function getContactList($surname = "") {
        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        if (!is_file(dirname(__FILE__).'/import/contacten.csv')) {
            $this->Error[] = "Bestand 'contacten.csv' niet gevonden. Plaats een bestand met contact ID's in de map 'import' en noem dit bestand 'contacten.csv'";
            return false;
        }
        /**
         * Step 1) Search for contact data
         */
        set_time_limit(1000);
        $contact_list = [];
        $contacts = file(dirname(__FILE__).'/import/contacten.csv',FILE_IGNORE_NEW_LINES);
        if ((is_array($contacts)) && (count($contacts)>0)) {
            foreach ($contacts as $contact) {
                $info = $this->mtr->getcontactinfo($contact);
                if ($info) {
                    $contact_list[] = [
                        "Identifier" => $contact,
                        "Handle" => $contact,
                        "CompanyName" => $info['company'],
                        "Initials" => "",
                        "SurName" => $info['name'],
                        'Address' => $info['address'],
                        'ZipCode' => $info['postcode'],
                        'City' => $info['city'],
                        'Country' => $info['countrycode'],
                        'PhoneNumber' => $info['phone'],
                        'FaxNumber' => $info['fax'],
                        'EmailAddress' => $info['email']];
                } else {
                    $this->Error[] = $this->mtr->getLastError();
                    return false;
                }
            }

        }



        /**
         * Step 2) provide feedback to WeFact
         */
        if(count($contact_list) > 0)
        {
            // Return handle list
            return $contact_list;
        }
        else
        {
            // No handles are found
            return array();
        }
    }

    /**
     * Update the nameservers for the given domain.
     *
     * @param string $domain The domain to be changed.
     * @param array $nameservers The new set of nameservers.
     * @return bool True if the update was succesfull; False otherwise;
     */
    function updateNameServers($domain, $nameservers = array()) {
        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        // TODO: check hostnames and create Glue records when hostnames are not there and domainname matches hostname
        // Remove the IP addresses from the nameservers array
        foreach ($nameservers as $index=>$nameserver) {
            if (($index == 'ns1ip') || ($index == 'ns2ip') || ($index == 'ns3ip')) {
                unset($nameservers[$index]);
            }
        }
        $response = $this->mtr->updatenameservers($domain, $nameservers);
        if (!$response) {
            $this->Error[] = $this->mtr->getLastError();
            return false;
        }
        return $response;
    }


    /**
     * Retrieve all DNS templates from registrar
     *
     * @return array List of all DNS templates
     */
    function getDNSTemplates() {

        $response 	= false;
        if($response === true)
        {
            $dns_templates = array();
            foreach($response['templates'] as $key => $_dns_template)
            {
                $dns_templates['templates'][$key]['id']      = $_dns_template['id'];
                $dns_templates['templates'][$key]['name']    = $_dns_template['name'];
            }

            return $dns_templates;
        }
        else
        {
            // Registrar has/supports no DNS templates
            $this->Error[] = 'DNS Templates worden niet ondersteund door de Metaregistrar API';
            return false;
        }
    }

    /**
     * Get the DNS zone of a domain
     *
     * @param string $domain The domain
     * @return array Array with DNS zone info and DNS records
     */
    function getDNSZone($domain) {
        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        $response 	= $this->mtr->getdnszone($domain);
        if($response) {
            $i = 0;
            $dns_zone = array();

            foreach($response as $record) {
                // optionally, you can define records as readonly, these records won't be editable in WeFact
                if(strtolower($record['type']) == 'soa') {
                    $record_type = 'records_readonly';
                }
                else  {
                    $record_type = 'records';
                }
                $dns_zone[$record_type][$i]['name']        = $record['name'];
                $dns_zone[$record_type][$i]['type']        = $record['type'];
                $dns_zone[$record_type][$i]['value']       = $record['content'];
                $dns_zone[$record_type][$i]['priority']    = $record['priority'];
                $dns_zone[$record_type][$i]['ttl']         = $record['ttl'];
                $dns_zone[$record_type][$i]['id']          = $i; // not required
                $i++;
            }

            return $dns_zone;
        }
        else {
            // DNS zone does not exist or API call failed
            $this->Error[] = $this->mtr->getLastError();
            return false;
        }
    }

    /**
     * Update the DNS zone of a domain at the registrar
     *
     * @param string $domain The domain
     * @param array  $dns_zone Array with DNS zone info and DNS records
     * @return array Array with DNS zone info & DNS records
     */
    function saveDNSZone($domain, $dns_zone) {
        if (!$this->mtr) {
            $this->mtr = new mtr($this->User, $this->Password, $this->Testmode);
        }
        /*
        if the registrar does not support a update command, but only add/delete commands
        use the getDNSZone command and compare it's output with the $dns_zone array
        this way you can check which records are edited and add/delete them accordingly
        */
        $oldzone = $this->getDNSZone($domain);
        $adds = [];
        $dels = [];

        foreach ($oldzone['records'] as $record) {
            if ((isset($record['priority'])) && ($record['priority']>0)) {
                $dels[] = ['type' => $record['type'], 'name' => $record['name'], 'content' => $record['value'], 'ttl' => $record['ttl'], 'priority' => $record['priority']];
            } else {
                $dels[] = ['type' => $record['type'], 'name' => $record['name'], 'content' => $record['value'], 'ttl' => $record['ttl']];
            }
        }
        foreach ($dns_zone['records'] as $record) {
            $tussen = '';
            if (strlen($record['name'])>0) {
                $tussen = '.';
            }
            if ((isset($record['priority'])) && ($record['priority']>0)) {
                $adds[] = ['type' => $record['type'], 'name' => $record['name'].$tussen.$domain, 'content' => $record['value'], 'ttl' => $record['ttl'], 'priority' => $record['priority']];
            } else {
                $adds[] = ['type' => $record['type'], 'name' => $record['name'].$tussen.$domain, 'content' => $record['value'], 'ttl' => $record['ttl']];
            }
        }

        $response = $this->mtr->updatednszone($domain, $adds, $dels);
        if (!$response) {
            $this->Error[] = $this->mtr->getLastError();
            return false;
        }
        return $response;


    }

    /**
     * Retrieve all SSL products from registrar
     *
     * @param string $ssl_type  Optional filter for SSL validation type
     * @return array
     */
    public function ssl_list_products($ssl_type = '')
    {
        $this->Error[] = "SSL Certificaten worden nog niet ondersteund door de Metaregistrar API";
        return false;

        /**
         * Step 1) get all SSL products filtered by the $ssl_type
         */
        $response 	= true;

        /**
         * Step 2) provide feedback to WeFact
         */
        $products_array = array();

        if($response === true)
        {
            foreach($response->response->products->product as $product)
            {
                $products_array[] = array(	'name' => $product->name,
                    'brand' => $product->brand,
                    'templatename' => $product->code,
                    'type' => 'domain'); // domain, extended or organization
            }
        }

        return $products_array;
    }

    /**
     * Get SSL product from registrar
     *
     * @param string $templatename     ID of the product as retrieved by ssl_list_products()
     * @return array
     */
    public function ssl_get_product($templatename)
    {
        $this->Error[] = "SSL Certificaten worden nog niet ondersteund door de Metaregistrar API";
        return false;

        /**
         * Step 1) get SSL product
         */
        $response 	= true;

        /**
         * Step 2) provide feedback to WeFact
         */
        $product_info = array();

        if($response === true)
        {
            $product_info['name'] 			= $response['name'];
            $product_info['brand']          = $response['brand'];
            $product_info['templatename'] 	= $response['id'];
            $product_info['type'] 			= $response['type']; // domain, extended or organization

            $product_info['wildcard']		        = ($response['wildcard'] == 'Y') ? TRUE : FALSE;
            $product_info['multidomain']			= ($response['multidomain'] == 'Y') ? TRUE : FALSE;
            $product_info['multidomain_included']	= ($response['domains']) ? $response['domains'] : 0;
            $product_info['multidomain_max']		= ($response['domains_max']) ? $response['domains_max'] : 0;

            // Pricing-periods
            $product_info['periods'] = array();
            if(isset($response['pricing']))
            {
                foreach($response['pricing']['period'] as $index => $period_fee)
                {
                    // please note: periods should be in years, not months
                    $product_info['periods'][] = array('periods' => (int) $period_fee['years'], 'price' => (float) $period_fee['price']);
                }
            }
        }

        return $product_info;
    }

    /**
     * Request the SSL certificate at the registrar
     *
     * @param array $ssl_info   Array with info regarding the SSL certificate
     * @param object $whois     Object with WHOIS data
     * @return bool
     */
    public function ssl_request_certificate($ssl_info, $whois)
    {
        $this->Error[] = "SSL Certificaten worden nog niet ondersteund door de Metaregistrar API";
        return false;

        /**
         * Step 1) request SSL certificate at registrar
         */
        $response 	= true;

        /**
         * Step 2) provide feedback to WeFact
         */
        if($response === FALSE)
        {
            $this->Error[] = 'ERROR MESSAGE';
            return FALSE;
        }

        return $response['ORDERID'];
    }

    /**
     * Retrieve the approver emailadresses for a SSL certificate from the registrar
     *
     * @param string $commonname    Domain name
     * @param string $templatename  ID of the product as retrieved by ssl_list_products()
     * @return array|bool
     */
    public function ssl_get_approver_list($commonname, $templatename)
    {
        $this->Error[] = "SSL Certificaten worden nog niet ondersteund door de Metaregistrar API";
        return false;

        /**
         * Step 1) get list of approver email adresses at registrar
         */
        $response 	= true;

        /**
         * Step 2) provide feedback to WeFact
         */
        if($response === FALSE)
        {
            $this->Error[] = 'ERROR MESSAGE';
            return FALSE;
        }

        return array_unique($response['approver_list']); // array with e-mailadresses
    }

    /**
     * Retrieve the status of a SSL certificate from the registrar
     *
     * @param string $ssl_order_id  Order id from a SSL certificate
     * @return array
     */
    public function ssl_get_request_status($ssl_order_id)
    {
        $$this->Error[] = "SSL Certificaten worden nog niet ondersteund door de Metaregistrar API";
        return false;

        /**
         * Step 1) get status of SSL certificate
         */
        $response 	= true;

        /**
         * Step 2) provide feedback to WeFact
         */
        $order_info = array();
        if($response === FALSE)
        {
            $order_info['status'] = 'error';
        }

        switch(strtolower($response['status']))
        {
            default:
            case 'pending':
                $order_info['status'] = 'inrequest';
                break;

            case 'failed':
                $order_info['status'] = 'error';
                break;

            case 'active':
                $order_info['status'] = 'install';
                $order_info['activation_date'] = $response['start_date'];
                $order_info['expiration_date'] = $response['end_date'];
                break;
        }

        return $order_info;
    }

    /**
     * Download the CSR of a SSL certificate from the registrar
     *
     * @param string $ssl_order_id  Order id from a SSL certificate
     * @return bool|string
     */
    public function ssl_download_ssl_certificate($ssl_order_id)
    {
        $this->Error[] = "SSL Certificaten worden nog niet ondersteund door de Metaregistrar API";
        return false;
        /**
         * Step 1) download the SSL certificate
         */
        $response 	= true;

        /**
         * Step 2) provide feedback to WeFact
         */
        if($response === FALSE)
        {
            $this->Error[] = 'ERROR MESSAGE';
            return FALSE;
        }

        return $response['certificate'];
    }

    /**
     * Reissue a SSL certificate at the registrar
     *
     * @param string $ssl_order_id  Order id from a SSL certificate
     * @param array $ssl_info       Array with info regarding the SSL certificate
     * @param object $whois         Object with WHOIS data
     * @return bool
     */
    public function ssl_reissue_certificate($ssl_order_id, $ssl_info, $whois)
    {
        $this->Error[] = "SSL Certificaten worden nog niet ondersteund door de Metaregistrar API";
        return false;

        /**
         * Step 1) reissue the SSL certificate at the registrar
         */
        $response 	= true;

        /**
         * Step 2) provide feedback to WeFact
         */
        if($response === FALSE)
        {
            $this->Error[] = 'ERROR MESSAGE';
            return FALSE;
        }

        return $response['ORDERID'];
    }

    /**
     * Renew the SSL certificate at the registrar
     *
     * @param array $ssl_info       Array with info regarding the SSL certificate
     * @param object $whois         Object with WHOIS data
     * @return bool
     */
    public function ssl_renew_certificate($ssl_info, $whois)
    {
        $this->Error[] = "SSL Certificaten worden nog niet ondersteund door de Metaregistrar API";
        return false;

        /**
         * Step 1) renew the SSL certificate at the registrar
         */
        $response 	= true;

        /**
         * Step 2) provide feedback to WeFact
         */
        if($response === FALSE)
        {
            $this->Error[] = 'ERROR MESSAGE';
            return FALSE;
        }

        return $response['ORDERID'];
    }

    /**
     * Resend the approver email
     *
     * @param string $ssl_order_id      Order id from a SSL certificate
     * @param $approver_emailaddress    Approver emailaddress
     * @return bool
     */
    public function ssl_resend_approver_email($ssl_order_id, $approver_emailaddress)
    {
        $this->Error[] = "SSL Certificaten worden nog niet ondersteund door de Metaregistrar API";
        return false;

        /**
         * Step 1) resend the approver e-mail
         */
        $response 	= true;

        /**
         * Step 2) provide feedback to WeFact
         */
        if($response === FALSE)
        {
            $this->Error[] = 'ERROR MESSAGE';
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Revoke SSL certificate at the registrar
     *
     * @param string $ssl_order_id      Order id from a SSL certificate
     * @return bool
     */
    public function ssl_revoke_ssl_certificate($ssl_order_id)
    {
        $this->Error[] = "SSL Certificaten worden nog niet ondersteund door de Metaregistrar API";
        return false;

        /**
         * Step 1) revoke the SSL certificate at the registrar
         */
        $response 	= true;

        /**
         * Step 2) provide feedback to WeFact
         */
        if($response === FALSE)
        {
            $this->Error[] = 'ERROR MESSAGE';
            return FALSE;
        }

        return TRUE;
    }


    /**
     * Get class version information.
     *
     * @return array()
     */
    static function getVersionInformation() {
        require_once(dirname(__FILE__)."/version.php");
        return $version;
    }

}
?>