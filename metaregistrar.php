<?php
require_once("3rdparty/domain/IRegistrar.php");
require_once("3rdparty/domain/standardfunctions.php");
require_once("3rdparty/domain/metaregistrar/autoloader.php");

/**
 * -------------------------------------------------------------------------------------
 * Metaregistrar - IRegistrar
 *
 * Author		: Ewout de Graaf
 * Copyright	: (c) 2018 Metaregistrar BV
 * Version 		: 1.0
 *
 * CHANGE LOG:
 * -------------------------------------------------------------------------------------
 *  2017-08-18		E.W. de Graaf 		Initial version
 * -------------------------------------------------------------------------------------
 */

class metaregistrar implements IRegistrar
{
    public $User;
    public $Password;

    public $Error;
    public $Warning;
    public $Success;

    public $Period = 1;
    public $registrarHandles = array();

    private $ClassName;
    /**
     * @var \Metaregistrar\EPP\eppConnection
     */
    private $conn;
    /**
     * @var bool
     */
    private $loggedin;

    /**
     * metaregistrar constructor.
     */
    function __construct(){

        $this->ClassName = __CLASS__;

        $this->Error = array();
        $this->Warning = array();
        $this->Success = array();
        return true;


    }

    /**
     * metaregistrar destructor
     */
    function __destruct(){
        $this->logout();
        return true;
    }


    /*
     *
     * METAREGISTRAR CONNECTION FUNCTIONS
     * These functions make use of https://github.com/metaregistrar/php-epp-client for EPP connection
     *
     *
     *
     *
     *
     */

    /**
     * @return bool
     */
    private function login() {
        try {
            $this->conn = new Metaregistrar\EPP\metaregEppConnection();
            // Set parameters
            $this->conn->setHostname('ssl://eppl.metaregistrar.com');
            $this->conn->setPort(7000);
            $this->conn->setUsername($this->User);
            $this->conn->setPassword($this->Password);
            // Send EPP login command
            if ($this->conn->login()) {
//                $this->Success[] = "Succesfully logged-in to metaregistrar with user ".$this->User;
                $this->loggedin = true;
                return true;
            } else {
                $this->Error[] = "Unable to login with user id: ".$this->User;
                return false;
            }
        } catch (Metaregistrar\EPP\eppException $e) {
            $this->Error[] = $e->getMessage();
            return false;
        }
    }

    /**
     *
     */
    private function logout() {
        // Log out of EPP when still connected
        if ($this->conn) {
            if ($this->loggedin) {
                // When still logged-in, logout
                $this->conn->logout();
                $this->loggedin = false;
            }
            $this->conn->disconnect();
        }
    }

    /**
     * Create a new domain name
     * @param $domainname
     * @param $registrant
     * @param $adminc
     * @param $techc
     * @param $nameservers
     * @param $period
     * @param $authcode
     * @return bool
     */
    private function mtrcreatedomain($domainname, $registrant, $adminc, $techc, $nameservers, $period, $authcode) {
        if (!$this->loggedin) {
            if (!$this->login()) {
                return false;
            }
        }
        try {
            $domain = new \Metaregistrar\EPP\eppDomain($domainname);
            // Set domain parameters
            $domain->setRegistrant($registrant);
            $domain->addContact(new \Metaregistrar\EPP\eppContactHandle($adminc,\Metaregistrar\EPP\eppContactHandle::CONTACT_TYPE_ADMIN));
            $domain->addContact(new \Metaregistrar\EPP\eppContactHandle($techc,\Metaregistrar\EPP\eppContactHandle::CONTACT_TYPE_TECH));
            $domain->addContact(new \Metaregistrar\EPP\eppContactHandle($techc,\Metaregistrar\EPP\eppContactHandle::CONTACT_TYPE_BILLING));
            if (is_array($nameservers)) {
                foreach ($nameservers as $ns) {
                    if (strlen($ns) > 0) {
                        $domain->addHost(new \Metaregistrar\EPP\eppHost($ns));
                    }
                }
            }
            $domain->setPeriod($period);
            $domain->setPeriodUnit('y');
            $domain->setAuthorisationCode($authcode);
            // Make EPP request to create domain
            $create = new \Metaregistrar\EPP\eppCreateDomainRequest($domain);
            // Send the request
            if ($response = $this->conn->request($create)) {
                /* @var $response \Metaregistrar\EPP\eppCreateDomainResponse */
                // Process the response
                if (($response->getResultCode()==1000) || ($response->getResultCode()==1001)) {
                    return true;
                } else {
                    $this->Error[] = $response->getResultMessage().' '.$response->getResultReason();
                    return false;
                }
            } // ELSE function is caught by eppException
        } catch (Metaregistrar\EPP\eppException $e) {
            $this->Error[] = $e->getMessage();
            return false;
        }
        $this->Error[] = 'General error occurred while creating domain '.$domainname;
        return false;
    }

    /**
     * Request transfer of a domain name
     * @param $domainname
     * @param $registrant
     * @param $adminc
     * @param $techc
     * @param $nameservers
     * @param $period
     * @param $authcode
     * @return bool
     */
    private function mtrtransferdomain($domainname, $registrant, $adminc, $techc, $nameservers, $period, $authcode) {
        if (!$this->loggedin) {
            if (!$this->login()) {
                return false;
            }
        }
        try {
            // Set domain transfer parameters
            $domain = new \Metaregistrar\EPP\eppDomain($domainname);
            $domain->setRegistrant($registrant);
            $domain->addContact(new \Metaregistrar\EPP\eppContactHandle($adminc,\Metaregistrar\EPP\eppContactHandle::CONTACT_TYPE_ADMIN));
            $domain->addContact(new \Metaregistrar\EPP\eppContactHandle($techc,\Metaregistrar\EPP\eppContactHandle::CONTACT_TYPE_TECH));
            $domain->addContact(new \Metaregistrar\EPP\eppContactHandle($techc,\Metaregistrar\EPP\eppContactHandle::CONTACT_TYPE_BILLING));
            if (is_array($nameservers)) {
                foreach ($nameservers as $ns) {
                    if (strlen($ns) > 0) {
                        $domain->addHost(new \Metaregistrar\EPP\eppHost($ns));
                    }
                }
            }
            $domain->setPeriod($period);
            $domain->setPeriodUnit('y');
            $domain->setAuthorisationCode($authcode);
            // Create an EPP transfer request
            $transfer = new \Metaregistrar\EPP\metaregEppTransferExtendedRequest(\Metaregistrar\EPP\eppTransferRequest::OPERATION_REQUEST,$domain);
            // Send the EPP request
            if ($response = $this->conn->request($transfer)) {
                /* @var $response \Metaregistrar\EPP\eppTransferResponse */
                // Process the response
                if (($response->getResultCode()==1000) || ($response->getResultCode()==1001)) {
                    return true;
                } else {
                    $this->Error[] = $response->getResultMessage().' '.$response->getResultReason();
                    return false;
                }
            }
        } catch (Metaregistrar\EPP\eppException $e) {
            $this->Error[] = $e->getMessage();
            return false;
        }
        $this->Error[] = 'General error occurred while creating domain '.$domainname;
        return false;
    }

    private function mtrupdatecontact($handle, $whois) {
        if (!$this->loggedin) {
            if (!$this->login()) {
                return false;
            }
        }
        try {
            // Set the handle to be updated
            $contacthandle = new \Metaregistrar\EPP\eppContactHandle($handle);
            // Set the contact parameters for update
            $postalinfo = new \Metaregistrar\EPP\eppContactPostalInfo($whois->ownerInitials.' '.$whois->ownerSurName, $whois->ownerCity, $whois->ownerCountry, $whois->ownerCompanyName, $whois->ownerAddress, '', $whois->ownerZipCode);
            if (strlen($whois->ownerFaxNumber) > 0) {
                $whois->ownerFaxNumber = $whois->CountryCode.'.'.$whois->ownerFaxNumber;
            }
            if (strlen($whois->ownerPhoneNumber) > 0) {
                $whois->ownerPhoneNumber = $whois->CountryCode.'.'.$whois->ownerPhoneNumber;
            }
            $contact = new \Metaregistrar\EPP\eppContact($postalinfo, $whois->ownerEmailAddress, $whois->ownerPhoneNumber, $whois->ownerFaxNumber);
            // Create an EPP update request
            $update = new \Metaregistrar\EPP\eppUpdateContactRequest($contacthandle, null, null, $contact);
            // Send the EPP request
            if ($response = $this->conn->request($update)) {
                /* @var $response \Metaregistrar\EPP\eppUpdateContactResponse */
                // Process the response
                if ($response->getResultCode() == 1000) {
                    return true;
                } else {
                    $this->Error[] = $response->getResultMessage(). ' '.$response->getResultReason();
                    return false;
                }
            }
        } catch (Metaregistrar\EPP\eppException $e) {
            $this->Error[] = $e->getMessage();
            return false;
        }
        $this->Error[] = 'General error occurred while updating contact '.$handle;
        return false;
    }

    /**
     * @param $whois
     * @return bool|string
     */
    private function mtrcreatecontact($whois) {
        if (!$this->loggedin) {
            if (!$this->login()) {
                return false;
            }
        }
        try {
            // Set the contact parameters
            $postalinfo = new \Metaregistrar\EPP\eppContactPostalInfo($whois->ownerInitials.' '.$whois->ownerSurName, $whois->ownerCity, $whois->ownerCountry, $whois->ownerCompanyName, $whois->ownerAddress, '', $whois->ownerZipCode);
            if (strlen($whois->ownerFaxNumber) > 0) {
                if (strpos('+31.',$whois->ownerFaxNumber)===false) {
                    $whois->ownerFaxNumber = $whois->CountryCode . '.' . $whois->ownerFaxNumber;
                }
            }
            if (strlen($whois->ownerPhoneNumber) > 0) {
                if (strpos('+31.',$whois->ownerPhoneNumber)===false) {
                    $whois->ownerPhoneNumber = $whois->CountryCode.'.'.$whois->ownerPhoneNumber;
                }
            }
            $contact = new \Metaregistrar\EPP\eppContact($postalinfo, $whois->ownerEmailAddress, $whois->ownerPhoneNumber, $whois->ownerFaxNumber);
            // Create an EPP contact:create request
            $create = new \Metaregistrar\EPP\eppCreateContactRequest($contact);
            // Send the request
            if ($response = $this->conn->request($create)) {
                /* @var $response \Metaregistrar\EPP\eppCreateContactResponse */
                // Handle the response
                if ($response->getResultCode() == 1000) {
                    return $response->getContactId();
                } else {
                    $this->Error[] = $response->getResultMessage(). ' '.$response->getResultReason();
                    return false;
                }
            }
        } catch (Metaregistrar\EPP\eppException $e) {
            $this->Error[] = $e->getMessage();
            return false;
        }
        $this->Error[] = 'General error occurred while creating contact';
        return false;
    }


    /**
     * Retrieve information on a domain name
     * @param $domainname
     * @return array|bool
     */
    private function mtrgetdomaininfo($domainname) {
        if (!$this->loggedin) {
            if (!$this->login()) {
                return false;
            }
        }
        try {
            // Set the domain to be infoed
            $domain = new \Metaregistrar\EPP\eppDomain($domainname);
            // Create the EPP domain:info request
            $request = new \Metaregistrar\EPP\eppInfoDomainRequest($domain);
            // Send the EPP request
            if ($response = $this->conn->request($request)) {
                /* @var $response \Metaregistrar\EPP\eppInfoDomainResponse */
                // Handle the response
                $info  = [];
                $info['registrant'] = $response->getDomainRegistrant();
                $info['admin-c'] = $response->getDomainContact(\Metaregistrar\EPP\eppContactHandle::CONTACT_TYPE_ADMIN);
                $info['tech-c'] = $response->getDomainContact(\Metaregistrar\EPP\eppContactHandle::CONTACT_TYPE_TECH);
                $info['authcode'] = $response->getDomainAuthInfo();
                $info['credate'] = $response->getDomainCreateDate();
                $info['expdate'] = $response->getDomainExpirationDate();
                $info['nameservers'] = explode(',',$response->getDomainNameserversCSV());
                return $info;
            }
        } catch (Metaregistrar\EPP\eppException $e) {
            $this->Error[] = $e->getMessage();
            return false;
        }
        $this->Error[] = 'General error occurred while retrieving domain info for '.$domainname;
        return false;
    }

    /**
     * Get all contents of a contact object
     * @param string $handle
     * @return bool|array
     */
    private function mtrgetcontactinfo($handle) {
        if (!$this->loggedin) {
            if (!$this->login()) {
                return false;
            }
        }
        try {
            // Set the contact handle to be queried
            $contact = new \Metaregistrar\EPP\eppContactHandle($handle);
            // Create an EPP contact:info request
            $request = new \Metaregistrar\EPP\eppInfoContactRequest($contact);
            // Send the request
            if ($response = $this->conn->request($request)) {
                /* @var $response \Metaregistrar\EPP\eppInfoContactResponse */
                // Handle the response
                $info  = [];
                $postalinfo = $response->getContactPostalInfo();
                /* @var $postalinfo \Metaregistrar\EPP\eppContactPostalInfo */
                $info['company'] = $postalinfo[0]->getOrganisationName();
                $info['name'] = $postalinfo[0]->getName();
                $info['address'] = $postalinfo[0]->getStreet(0);
                $info['postcode'] = $postalinfo[0]->getZipcode();
                $info['city'] = $postalinfo[0]->getCity();
                $info['countrycode'] = $postalinfo[0]->getCountrycode();
                $info['phone'] = $response->getContactVoice();
                $info['fax'] = $response->getContactFax();
                $info['email'] = $response->getContactEmail();
                return $info;
            }
        } catch (Metaregistrar\EPP\eppException $e) {
            $this->Error[] = $e->getMessage();
            return false;
        }
        $this->Error[] = 'General error occurred while retrieving contact info for '.$handle;
        return false;
    }

    /**
     * @param string $domainname
     * @param bool $autorenew
     * @return bool
     */
    private function mtrupdateautorenew($domainname, $autorenew) {
        if (!$this->loggedin) {
            if (!$this->login()) {
                return false;
            }
        }
        try {
            // Set the domain to be updated
            $domain = new \Metaregistrar\EPP\eppDomain($domainname);
            // Create EPP domain:update request with autorenew extension
            $update = new \Metaregistrar\EPP\metaregEppAutorenewRequest($domain,$autorenew);
            // Send the request
            if ($response = $this->conn->request($update)) {
                // Handle the response
                if ($response->getResultCode() == 1000) {
                    return true;
                }
            } else {
                $this->Error[] = $response->getResultMessage();
                return false;
            }
        } catch (Metaregistrar\EPP\eppException $e) {
            $this->Error[] = $e->getMessage();
            return false;
        }
        $this->Error[] = 'General error occurred while updating nameservers info for '.$domainname;
        return false;
    }

    /**
     * Update the nameservers of the domain name. Specify the new set of nameservers as wanted, the system will determine the changes to be made
     * @param $domainname
     * @param $nameservers
     * @return bool
     */
    private function mtrupdatenameservers($domainname, $nameservers) {
        if (!$this->loggedin) {
            if (!$this->login()) {
                return false;
            }
        }
        try {
            // First get the current nameservers of this domain name
            $info = $this->mtrgetdomaininfo($domainname);
            if ($info) {
                // Process the current nameservers to see if the new ones are already in there
                $oldns = $info['nameservers'];
                foreach ($nameservers as $index=>$ns) {
                    if (in_array($ns,$oldns)) {
                        // If the new nameservers are already in the current nameservers, nothing has to be removed or added
                        $ind = array_search($ns,$oldns);
                        unset($oldns[$ind]);
                        unset($nameservers[$index]);
                    }
                }
                // Check if we still have nameservers left to add or remove
                if ((count($nameservers) > 0) || (count($oldns) > 0)) {
                    $domain = new \Metaregistrar\EPP\eppDomain($domainname);
                    $add = null;
                    // There are still nameservers left to add
                    if (count($nameservers) > 0) {
                        $add = new \Metaregistrar\EPP\eppDomain($domainname);
                        foreach ($nameservers as $ns) {
                            if (strlen($ns) > 0) {
                                $add->addHost(new \Metaregistrar\EPP\eppHost($ns));
                            }
                        }
                    }
                    $rem = null;
                    // There are still nameservers left to be removed
                    if (count($oldns) > 0) {
                        $rem = new \Metaregistrar\EPP\eppDomain($domainname);
                        foreach ($oldns as $ns) {
                            if (strlen($ns) > 0) {
                                $rem->addHost(new \Metaregistrar\EPP\eppHost($ns));
                            }
                        }
                    }
                    // Create the EPP domain:update request
                    $update = new \Metaregistrar\EPP\eppUpdateDomainRequest($domain, $add, $rem, null);
                    // Send the request
                    if ($reponse = $this->conn->request($update)) {
                        /* @var $response \Metaregistrar\EPP\eppUpdateDomainResponse */
                        // Handle the response
                        if ($response->getResultCode() == 1000) {
                            return true;
                        }
                    }
                } else {
                    return true;
                }
            } else {
                return false;
            }
        } catch (Metaregistrar\EPP\eppException $e) {
            $this->Error[] = $e->getMessage();
            return false;
        }
        $this->Error[] = 'General error occurred while updating nameservers info for '.$domainname;
        return false;
    }

    /**
     * Check if a domain name is free or taken
     * @param string $domainname
     * @return bool
     */
    private function mtrcheckdomain($domainname) {
        if (!$this->loggedin) {
            if (!$this->login()) {
                return false;
            }
        }
        try {
            // The domain name to be checked
            $domain = new \Metaregistrar\EPP\eppDomain($domainname);
            // Create an EPP domain:check request
            $check = new \Metaregistrar\EPP\eppCheckDomainRequest($domain);
            // Send the request
            if ($response = $this->conn->request($check)) {
                /* @var $response \Metaregistrar\EPP\eppCheckDomainResponse */
                // Handle the response
                $test = $response->getCheckedDomains();
                return $test[0]['available'];
            } else {
                $this->Error[] = 'Error checking domain name '.$domainname;
                return false;
            }

        } catch (Metaregistrar\EPP\eppException $e) {
            $this->Error[] = $e->getMessage();
            return false;
        }
    }

    /**
     * Cancel a domain name
     * @param string $domainname
     * @return bool
     */
    private function mtrdeletedomain($domainname) {
        if (!$this->loggedin) {
            if (!$this->login()) {
                return false;
            }
        }
        try {
            // Set the domain to be deleted
            $domain = new \Metaregistrar\EPP\eppDomain($domainname);
            // Create an EPP domain:delete request
            $delete = new \Metaregistrar\EPP\eppDeleteDomainRequest($domain);
            // Send the EPP request
            if ($response = $this->conn->request($delete)) {
                /* @var $response \Metaregistrar\EPP\eppDeleteResponse */
                // Handle the response
                if (($response->getResultCode() == 1000) || ($response->getResultCode() == 1001)) {
                    return true;
                }
            } else {
                $this->Error[] = 'Error deleting domain name '.$domainname;
                return false;
            }

        } catch (Metaregistrar\EPP\eppException $e) {
            $this->Error[] = $e->getMessage();
            return false;
        }
        $this->Error[] = 'General error deleting domain name '.$domainname;
        return false;
    }

    /**
     * Put a domain name on clientHold or remove the clientHold
     * @param $domainname
     * @param $lock
     * @return bool
     */
    function mtrlockdomain($domainname, $lock) {
        if (!$this->loggedin) {
            if (!$this->login()) {
                return false;
            }
        }
        try {
            // Set the domain name to be locked
            $domain = new \Metaregistrar\EPP\eppDomain($domainname);
            // Set the lock parameters, which lock to apply
            $change = new \Metaregistrar\EPP\eppDomain($domainname);
            $change->addStatus(\Metaregistrar\EPP\eppDomain::STATUS_CLIENT_HOLD);
            if ($lock) {
                // Add the lock status
                $update = new \Metaregistrar\EPP\eppUpdateDomainRequest($domain,$change,null,null);
            } else {
                // Remove the lock status
                $update = new \Metaregistrar\EPP\eppUpdateDomainRequest($domain, null,$change,null);
            }
            // Send the EPP request
            if ($response = $this->conn->request($update)) {
                /* @var $response \Metaregistrar\EPP\eppUpdateDomainResponse */
                // Handle the response
                if (($response->getResultCode() == 1000) || ($response->getResultCode() == 1001)) {
                    return true;
                }
            } else {
                $this->Error[] = 'Error locking domain name '.$domainname;
                return false;
            }

        } catch (Metaregistrar\EPP\eppException $e) {
            $this->Error[] = $e->getMessage();
            return false;
        }
        $this->Error[] = 'General error occurred locking domain name '.$domainname;
        return false;
    }




    /*
     *
     * STANDARD Wefact functions
     * These functions come with the standard Wefact implemenation and call the metaregistrar functions where applicable
     *
     *
     *
     *
     *
     */

    /**
     * Check whether a domain is already registered or not.
     *
     * @param 	string	 $domain	The name of the domain that needs to be checked.
     * @return 	boolean 			True if free, False if not free, False and $this->Error[] in case of error.
     */
    function checkDomain($domain) {
        return $this->mtrcheckdomain($domain);
    }


//		/**
//		 * EXAMPLE IF A FUNCTION IS NOT SUPPORTED
//		 * Check whether a domain is already registered or not.
//		 *
//		 * @param 	string	 $domain	The name of the domain that needs to be checked.
//		 * @return 	boolean 			True if free, False if not free, False and $this->Error[] in case of error.
//		 */
//		function checkDomain($domain) {
//			$this->Warning[] = 'Metaregistrar:  checking availability for domain '.$domain.' cannot be checked via the API.';
//
//			if($this->caller == 'register'){
//				return true;
//			}elseif($this->caller == 'transfer'){
//				return false;
//			}
//		}


    /**
     * Register a new domain
     *
     * @param 	string	$domain			The domainname that needs to be registered.
     * @param 	array	$nameservers	The nameservers for the new domain.
     * @param 	array	$whois			The customer information for the domain's whois information.
     * @return 	bool					True on success; False otherwise.
     */
    function registerDomain($domain, $nameservers = array(), $whois = null) {

        /** if you use DNS management, the following variables are also available
         * $this->DNSTemplateID
         * $this->DNSTemplateName
         */

        /**
         * Step 1) obtain an owner handle
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

            // If a new handle is created or found, store in array. WeFact will store this data, which will result in faster registration next time.
            $this->registrarHandles['owner'] = $ownerHandle;
        } else {
            // If no handle can be created, because data is missing, quit function
            $this->Error[] = sprintf("Metaregistrar: No domain owner contact given for domain '%s'.", $domain);
            return false;
        }

        /**
         * Step 2) obtain an admin handle
         */
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

            // If a new handle is created or found, store in array. WeFact will store this data, which will result in faster registration next time.
            $this->registrarHandles['admin'] = $adminHandle;
        } else {
            // If no handle can be created, because data is missing, quit function
            $this->Error[] = sprintf("Metaregistrar: No domain admin contact given for domain '%s'.", $domain);
            return false;
        }

        /**
         * Step 3) obtain a tech handle
         */
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

            // If a new handle is created or found, store in array. WeFact will store this data, which will result in faster registration next time.
            $this->registrarHandles['tech'] = $techHandle;
        } else {
            // If no handle can be created, because data is missing, quit function
            $this->Error[] = sprintf("Metaregistrar: No domain tech contact given for domain '%s'.", $domain);
            return false;
        }

        /**
         * Step 4) obtain nameserver handles
         */
        // If your system uses nameserver groups or handles, you can check the $nameserver array and match these hostnames with your own system.

        /**
         * Step 5) check your own default settings
         */
        // Determine period for registration in years, based on your TLD properties.
        // $this->Period is also used in WeFact, for determining the renewal date.
        $this->Period = 1;

        /**
         * Step 6) register domain
         */
        // Start registering the domain, you can use $domain, $ownerHandle, $adminHandle, $techHandle, $nameservers
        $authcode = '$KLfdcua0';
        $response = $this->mtrcreatedomain($domain, $ownerHandle, $adminHandle, $techHandle, $nameservers, $this->Period, $authcode);

        /**
         * Step 7) provide feedback to WeFact
         */
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

        /** if you use DNS management, the following variables are also available
         * $this->DNSTemplateID
         * $this->DNSTemplateName
         */

        /**
         * Step 1) obtain an owner handle
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
            $this->Error[] = sprintf("Metaregistrar: No domain owner contact given for domain '%s'.", $domain);
            return false;
        }

        /**
         * Step 2) obtain an admin handle
         */
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
            $this->Error[] = sprintf("Metaregistrar: No domain admin contact given for domain '%s'.", $domain);
            return false;
        }

        /**
         * Step 3) obtain a tech handle
         */
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
            $this->Error[] = sprintf("Metaregistrar: No domain tech contact given for domain '%s'.", $domain);
            return false;
        }

        /**
         * Step 4) obtain nameserver handles
         */
        // If your system uses nameserver groups or handles, you can check the $nameserver array and match these hostnames with your own system.

        /**
         * Step 5) check your own default settings
         */
        // Determine period for transfer in years, based on your TLD properties.
        // $this->Period is also used in WeFact, for determining the renewal date.
        $this->Period = 1;

        /**
         * Step 6) transfer domain
         */
        // Start transferring the domain, you can use $domain, $ownerHandle, $adminHandle, $techHandle, $nameservers, $authcode
        $response = $this->mtrtransferdomain($domain, $ownerHandle, $adminHandle, $techHandle, $nameservers, $this->Period, $authcode);

        /**
         * Step 7) provide feedback to WeFact
         */
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

        if($delType == "end"){
            return $this->setDomainAutoRenew($domain, false);
        } else {
            /**
             * Step 1) delete domain
             */
            // Delete the domain, you can use $domain
            return $this->mtrdeletedomain($domain);
        }
    }


    /**
     * Get all available information of the given domain
     *
     * @param 	mixed 	$domain		The domain for which the information is requested.
     * @return	array|bool			The array containing all information about the given domain
     */
    function getDomainInformation($domain) {

        /**
         * Step 1) query domain
         */
        // Query the domain, you can use $domain

        /**
         * Step 2) provide feedback to WeFact
         */
        if($info= $this->mtrgetdomaininfo($domain)) {
            $whois = new whois();
            $whois->ownerHandle = $info['registrant'];
            $whois->adminHandle = $info['admin-c'];
            $whois->techHandle 	= $info['tech-c'];

            // Return array with data
            $response = array(	"Domain" => $domain,
                "Information" => array(	"nameservers" => $info['nameservers'],
                    "whois" => $whois, // Whois object
                    "expiration_date" => $info['expdate'],  // Empty or date in yyyy-mm-dd
                    "registration_date" => $info['regdate'],  // Empty or date in yyyy-mm-dd
                    "authkey" => $info['authcode']));
            return $response;
        }
        else {
            // No information can be found
            $this->Error[] 	= sprintf("Metaregistrar: Error while retrieving information about domain name '%s'", $domain);
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
                $info = $this->mtrgetdomaininfo($domain);
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
        /**
         * Step 1) (un)lock domain
         */
        // (un)lock the domain, you can use $domain and $lock
        return $this->mtrlockdomain($domain, $lock);
    }

    /**
     * Change the autorenew state of the given domain. When autorenew is enabled, the domain will be extended.
     *
     * @param 	string	$domain			The domainname to change the autorenew setting for,
     * @param 	bool	$autorenew		The new autorenew setting (True = On|False = Off)
     * @return	bool					True when the setting is succesfully changed; False otherwise
     */
    function setDomainAutoRenew($domain, $autorenew = true) {
        /**
         * Step 1) change autorenew for domain
         */
        // change autorenew for the domain, you can use $domain and $autorenew
        /**
         * Step 2) provide feedback to WeFact
         */
        return $this->mtrupdateautorenew($domain, $autorenew);
    }

    /**
     * Get EPP code/token
     *
     * @param mixed $domain
     * @return
     */
    public function getToken($domain){
        /**
         * Step 1) get EPP code/token
         */
        $response 	= $this->mtrgetdomaininfo($domain);

        /**
         * Step 2) provide feedback to WeFact
         */
        if ($response)
        {
            // EPP code is retrieved
            return $response['authcode'];
        }
        else
        {
            // EPP code cannot be retrieved
            $this->Error[] 	= sprintf("Metaregistrar: Error while retreiving authorization code '%s'", $domain);
            return false;
        }
    }

    /**
     * getSyncData()
     * Check domain information for one or more domains
     * @param mixed $list_domains	Array with list of domains. Key is domain, value must be filled.
     * @return mixed $list_domains
     */
    public function getSyncData($list_domains) {

        /**
         * There are two scenario's for retrieving the information
         * 1. Get the domain list which provide sufficient information for this function
         * 2. We must request the info per domain. Take care of script timeout
         *
         * Both scenario's can be found below
         */
        $scenario = 'onebyone'; // list | onebyone

        if($scenario == 'list')
        {
            /**
             * Scenario 1: Get the domain list which provide sufficient information for this function
             */

            // First copy the array to $check_domains, so we can check if domains doesn't exist anymore
            $check_domains = $list_domains;

            // Ask registrar for information of all domains
            $response = array(); // Array with all domains and their information

            if($response === FALSE)
            {
                $this->Error[] = 'Domain(s) could not be retrieved at the registrar';
                return FALSE;
            }

            // loop trough the domain list from the registrar
            foreach($response as $_domain_info)
            {
                $domain_name 		= 'wefact.com';

                if(!isset($list_domains[$domain_name]))
                {
                    // Not in list to sync
                    continue;
                }

                // Add data
                $nameservers_array  = array('ns1.wefact.com','ns2.wefact.com');
                $expirationdate     = '2014-05-01';
                $auto_renew         = 'on'; // or 'off'

                // extend the list_domains array with data from the registrar
                $list_domains[$domain_name]['Information']['nameservers']        = $nameservers_array;
                $list_domains[$domain_name]['Information']['expiration_date']    = (isset($expirationdate)) ? $expirationdate : '';
                $list_domains[$domain_name]['Information']['auto_renew']         = (isset($auto_renew)) ? $auto_renew : '';

                $list_domains[$domain_name]['Status'] = 'success';

                //unset($check_domains[$domain_key]);
            }

            // add errors to the domains that weren't found with the registrar
            if(count($check_domains) > 0)
            {
                foreach($check_domains as $domain_key => $value)
                {
                    $list_domains[$domain_key]['Status']    = 'error';
                    $list_domains[$domain_key]['Error_msg'] = 'Domain not found';
                }
            }

            // Return list
            return $list_domains;
        }
        elseif($scenario == 'onebyone')
        {
            /**
             * Scenario 2: We must request the info per domain. Take care of script timeout
             */

            $max_domains_to_check = 1;

            $checked_domains = 0;
            // Check domain one for one
            foreach($list_domains as $domain_name => $value)
            {
                // Ask registrar for information of domain
                $response = array(); // Array with domain information
                $info = $this->mtrgetdomaininfo($domain_name);
                if(!$info)
                {
                    $list_domains[$domain_name]['Status']    = 'error';
                    $list_domains[$domain_name]['Error_msg'] = 'Domain not found';
                    continue;
                }

                // Add data
                $nameservers_array  = $info['nameservers'];
                $expirationdate     = $info['expdate'];
                $registrationdate   = $info['regdate'];
                $auto_renew         = 'on'; // or 'off'

                // extend the list_domains array with data from the registrar
                $list_domains[$domain_name]['Information']['nameservers']        = $nameservers_array;
                $list_domains[$domain_name]['Information']['expiration_date']    = (isset($expirationdate)) ? $expirationdate : '';
                $list_domains[$domain_name]['Information']['registration_date']  = (isset($registrationdate)) ? $registrationdate : '';
                $list_domains[$domain_name]['Information']['auto_renew']         = (isset($auto_renew)) ? $auto_renew : '';

                $list_domains[$domain_name]['Status'] = 'success';

                // Increment counter
                $checked_domains++;

                // Stop loop after max domains
                if($checked_domains > $max_domains_to_check)
                {
                    break;
                }
            }


            // Return list  (domains which aren't completed with data, will be synced by a next cronjob)
            return $list_domains;
        }
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
        /**
         * Step 1) update WHOIS data for domain.
         */
        // update owner WHOIS for the domain, you can use $domain and $whois
        $response 	= true;
        $error_msg 	= '';

        /**
         * Step 2) provide feedback to WeFact
         */
        if($response === true)
        {
            // update is succesfull
            return true;
        }
        else
        {
            // update failed
            $this->Error[] 	= sprintf("Metaregistrar: Error while changing contact for '%s': %s", $domain, $error_msg);
            return false;
        }
    }

    /**
     * get domain whois handles
     *
     * @param mixed $domain
     * @return array|bool with handles
     */
    function getDomainWhois($domain) {
        /**
         * Step 1) get handles for WHOIS contacts
         */
        $info = $this->mtrgetdomaininfo($domain);
        /**
         * Step 2) provide feedback to WeFact
         */
        if ($info) {
            $contacts = [];
            $contacts['ownerHandle'] 	= $info['registrant'];
            $contacts['adminHandle'] 	= $info['admin-c'];
            $contacts['techHandle'] 	= $info['tech-c'];
            return $contacts;
        }

        return false;
    }

    /**
     * Create a new whois contact
     *
     * @param 	array		 $whois		The whois information for the new contact.
     * @param 	mixed 	 	 $type		The contact type. This is only used to access the right data in the $whois object.
     * @return	bool					Handle when the new contact was created succesfully; False otherwise.
     */
    function createContact($whois, $type = HANDLE_OWNER) {

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
         * Step 1) Create the contact
         */
        // Create the contact
        $handle 	= $this->mtrcreatecontact($whois);

        /**
         * Step 2) provide feedback to WeFact
         */
        if($handle)
        {
            // A handle is created
            return $handle;
        }
        else
        {
            // Creating handle failed
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
        return $this->mtrupdatecontact($handle, $whois);
    }

    /**
     * Get information availabe of the requested contact.
     *
     * @param string $handle The handle of the contact to request.
     * @return array Information available about the requested contact.
     */
    function getContact($handle) {
        /**
         * Step 1) Create the contact
         */
        // Create the contact
        $response 	= $this->mtrgetcontactinfo($handle);
        $whois 		= new whois();

        /**
         * Step 2) provide feedback to WeFact
         */
        if($response)
        {
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
            $this->Error[] 	= sprintf("Metaregistrar: Error while retrieving contact %s", $handle);
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
        // Function to search contact by whois data is not supported
        $this->Error[] = 'Searching contact handle by providing contact data is not supported by the Metaregistrar API';
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
                $info = $this->mtrgetcontactinfo($contact);
                $contact_list[] = ["Handle" 		=> $contact,
                    "CompanyName"	=> $info['company'],
                    "Initials"		=> "",
                    "SurName" 		=> $info['name'],
                    'Address'       => $info['address'],
                    'ZipCode'       => $info['postcode'],
                    'City'          => $info['city'],
                    'Country'       => $info['countrycode'],
                    'PhoneNumber'   => $info['phone'],
                    'FaxNumber'     => $info['fax'],
                    'EmailAddress'  => $info['email']];
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
        /**
         * Step 1) update nameservers for domain
         */
        // Remove the IP addresses from the nameservers array
        // TODO: check hostnames and create Glue records when hostnames are not there
        foreach ($nameservers as $index=>$nameserver) {
            if (($index == 'ns1ip') || ($index == 'ns2ip') || ($index == 'ns3ip')) {
                unset($nameservers[$index]);
            }
        }
        $response = $this->mtrupdatenameservers($domain, $nameservers);
        /**
         * Step 2) provide feedback to WeFact
         */
        return $response;
    }


    /**
     * Retrieve all DNS templates from registrar
     *
     * @return array List of all DNS templates
     */
    function getDNSTemplates() {
        /**
         * Step 1) get all DNS templates
         */
        $response 	= false;

        /**
         * Step 2) provide feedback to WeFact
         */
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
            $this->Error[] = 'DNS Templates could not be retrieved at the registrar';
            return FALSE;
        }
    }

    /**
     * Get the DNS zone of a domain
     *
     * @param string $domain The domain
     * @return array Array with DNS zone info and DNS records
     */
    function getDNSZone($domain) {
        /**
         * Step 1) get DNS zone
         */
        $response 	= true;

        /**
         * Step 2) provide feedback to WeFact
         */
        if($response === true)
        {
            $i = 0;
            $dns_zone = array();

            foreach($response['records'] as $record)
            {
                // optionally, you can define records as readonly, these records won't be editable in WeFact
                if(strtolower($record['type']) == 'soa')
                {
                    $record_type = 'records_readonly';
                }
                else
                {
                    $record_type = 'records';
                }

                $dns_zone[$record_type][$i]['name']        = $record['name'];
                $dns_zone[$record_type][$i]['type']        = $record['type'];
                $dns_zone[$record_type][$i]['value']       = $record['value'];
                $dns_zone[$record_type][$i]['priority']    = $record['prio'];
                $dns_zone[$record_type][$i]['ttl']         = $record['ttl'];
                $dns_zone[$record_type][$i]['id']          = $record['id']; // not required

                $i++;
            }

            return $dns_zone;
        }
        else
        {
            // DNS zone does not exist or API call failed
            return FALSE;
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

        /*
        if the registrar does not support a update command, but only add/delete commands
        use the getDNSZone command and compare it's output with the $dns_zone array
        this way you can check which records are edited and add/delete them accordingly
        */

        /**
         * Step 1) update DNS zone at registrar
         */
        $response 	= true;

        /**
         * Step 2) provide feedback to WeFact
         */
        if($response === true)
        {
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }

    /**
     * Retrieve all SSL products from registrar
     *
     * @param string $ssl_type  Optional filter for SSL validation type
     * @return array
     */
    public function ssl_list_products($ssl_type = '')
    {
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
        require_once("3rdparty/domain/metaregistrar/version.php");
        return $version;
    }

}
?>