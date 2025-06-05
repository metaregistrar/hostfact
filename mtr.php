<?php
require_once("3rdparty/domain/metaregistrar/autoloader.php");

class mtr {

    private $username;
    private $password;
    private $testmode;
    
    /**
     * The connection to Metaregistrar EPP
     * @var \Metaregistrar\EPP\eppConnection
     */
    private $conn;
    /**
     * Indicates if the user is loggedin, useful for repetitive functions and cleanup
     * @var bool
     */
    private $loggedin;
    
    function __construct($username, $password, $testmode = false) {
        $this->username = $username;
        $this->password = $password;
        $this->testmode = $testmode;
    }
    
    function __destruct() {
        
    }

    /*
     * METAREGISTRAR CONNECTION FUNCTIONS
     * These functions make use of https://github.com/metaregistrar/php-epp-client for EPP connection
     */

    /**
     * @return bool
     */
    private function login() {
        try {
            $this->conn = new Metaregistrar\EPP\metaregEppConnection();
            // Set parameters
            if ($this->testmode) {
                $this->conn->setHostname('ssl://epp-ote.metaregistrar.com');
            } else {
                $this->conn->setHostname('ssl://epp.metaregistrar.com');
            }

            $this->conn->setPort(7000);
            $this->conn->setUsername($this->username);
            $this->conn->setPassword($this->password);
            $this->conn->setConnectionComment("HostFact user");
            // Send EPP login command
            if ($this->conn->login()) {
//                $this->Success[] = "Succesfully logged-in to metaregistrar with user ".$this->User;
                $this->loggedin = true;
                return true;
            } else {
                $this->Error[] = "Unable to login with user id: ".$this->username;
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


    private function generateauth($length = 12) {
        srand((double)microtime()*1000000);
        $str = '';
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789=+#@%_';
        $max = strlen($characters) - 1;
        for ($i = 0; $i < $length; $i++) {
            $rand = mt_rand(0, $max);
            $str .= $characters[$rand];
        }
        return $str;
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
    public function createdomain($domainname, $registrant, $adminc, $techc, $nameservers, $period, $authcode) {
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
                        if (!filter_var($ns, FILTER_VALIDATE_IP)) {
                            $domain->addHost(new \Metaregistrar\EPP\eppHost($ns));
                        }
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
        $this->Error[] = 'Algemene fout opgetreden bij aanvragen van domeinnaam '.$domainname;
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
    public function transferdomain($domainname, $registrant, $adminc, $techc, $nameservers, $period, $authcode) {
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
                        if (!filter_var($ns, FILTER_VALIDATE_IP)) {
                            $domain->addHost(new \Metaregistrar\EPP\eppHost($ns));
                        }
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
        $this->Error[] = 'Algemene fout opgetreden bij aanvragen van domeinnaam '.$domainname;
        return false;
    }

    public function updatecontact($handle, $whois) {
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
        $this->Error[] = 'Algemene fout opgetreden bij bijwerken van contact '.$handle;
        return false;
    }

    /**
     * @param $whois
     * @return bool|string
     */
    public function createcontact($whois) {
        if (!$this->loggedin) {
            if (!$this->login()) {
                return false;
            }
        }
        try {
            // Set the contact parameters
            $postalinfo = new \Metaregistrar\EPP\eppContactPostalInfo(htmlspecialchars_decode($whois->ownerInitials).' '.htmlspecialchars_decode($whois->ownerSurName), $whois->ownerCity, $whois->ownerCountry, htmlspecialchars_decode($whois->ownerCompanyName), htmlspecialchars_decode($whois->ownerAddress), '', $whois->ownerZipCode);
            if (strlen($whois->ownerFaxNumber) > 0) {
                if (strpos($whois->ownerFaxNumber,'+31.')===false) {
                    $whois->ownerFaxNumber = $whois->CountryCode . '.' . $whois->ownerFaxNumber;
                }
            }
            if (strlen($whois->ownerPhoneNumber) > 0) {
                if (strpos($whois->ownerPhoneNumber,'+31.')===false) {
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
        $this->Error[] = 'Algemene fout opgetreden bij aanmaken nieuw contact';
        return false;
    }


    /**
     * Retrieve information on a domain name
     * @param $domainname
     * @return array|bool
     */
    public function getdomaininfo($domainname) {
        if (!$this->loggedin) {
            if (!$this->login()) {
                return false;
            }
        }
        try {
            // Set the domain to be infoed
            $domain = new \Metaregistrar\EPP\eppDomain($domainname);
            // Create the EPP domain:info request
            $request = new \Metaregistrar\EPP\metaregInfoDomainRequest($domain);
            // Send the EPP request
            if ($response = $this->conn->request($request)) {
                /* @var $response \Metaregistrar\EPP\eppDnssecInfoDomainResponse */
                // Handle the response
                $info  = [];
                $info['registrant'] = $response->getDomainRegistrant();
                $info['admin-c'] = $response->getDomainContact(\Metaregistrar\EPP\eppContactHandle::CONTACT_TYPE_ADMIN);
                $info['tech-c'] = $response->getDomainContact(\Metaregistrar\EPP\eppContactHandle::CONTACT_TYPE_TECH);
                $info['authcode'] = $response->getDomainAuthInfo();
                $info['credate'] = $response->getDomainCreateDate();
                $info['expdate'] = $response->getDomainExpirationDate();
                $info['autorenew'] = $response->getAutoRenew();
                $info['nameservers'] = explode(',',$response->getDomainNameserversCSV());
                return $info;
            }
        } catch (Metaregistrar\EPP\eppException $e) {
            $this->Error[] = $e->getMessage();
            return false;
        }
        $this->Error[] = 'Algemene fout opgetreden bij opvragen domeinnaam informatie van '.$domainname;
        return false;
    }

    /**
     * Get all contents of a contact object
     * @param string $handle
     * @return bool|array
     */
    public function getcontactinfo($handle) {
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
        $this->Error[] = 'Algemene fout opgetreden bij opvragen contact informatie van '.$handle;
        return false;
    }

    /**
     * @param string $domainname
     * @param bool $autorenew
     * @return bool
     */
    public function updateautorenew($domainname, $autorenew) {
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
        $this->Error[] = 'Algemene fout opgetreden bij wijzigen nameserver informatie van '.$domainname;
        return false;
    }

    /**
     * Update the nameservers of the domain name. Specify the new set of nameservers as wanted, the system will determine the changes to be made
     * @param $domainname
     * @param $nameservers
     * @return bool
     */
    public function updatenameservers($domainname, $nameservers) {
        if (!$this->loggedin) {
            if (!$this->login()) {
                return false;
            }
        }
        try {
            // First get the current nameservers of this domain name
            $info = $this->getdomaininfo($domainname);
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
                    if ($response = $this->conn->request($update)) {
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
        $this->Error[] = 'Algemene fout opgetreden bij wijzigen nameserver informatie van '.$domainname;
        return false;
    }

    /**
     * Check if a domain name is free or taken
     * @param string $domainname
     * @return bool
     */
    public function checkdomain($domainname) {
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
    public function deletedomain($domainname) {
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
    function lockdomain($domainname, $lock) {
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


    /**
     * Get all DNS records for a specific domain name
     * @param string $domainname
     * @return array|bool
     */
    function getdnszone($domainname) {
        if (!$this->loggedin) {
            if (!$this->login()) {
                return false;
            }
        }
        try {
            $domain = new \Metaregistrar\EPP\eppDomain($domainname);
            $request = new \Metaregistrar\EPP\metaregInfoDnsRequest($domain);
            if ($response = $this->conn->request($request)) {
                /* @var $response \Metaregistrar\EPP\metaregInfoDnsResponse */
                $content = $response->getContent();
                return $content;
            } else {
                $this->Error[] = 'Error retrieving DNS for '.$domainname;
                return false;
            }

        } catch (Metaregistrar\EPP\eppException $e) {
            if ($e->getCode()==2303) {
                $this->creatednszone($domainname);
                $this->Error[] = "Er waren nog geen DNS gegevens bekend voor deze domeinnaam. De zone is nu aangemaakt, klik opnieuw op 'DNS beheer' om deze te bewerken";
                return false;
            } else {
                $this->Error[] = $e->getMessage();
                return false;
            }
        }
    }

    function creatednszone($domainname) {
        if (!$this->loggedin) {
            if (!$this->login()) {
                return false;
            }
        }
        try {
            $domain = new \Metaregistrar\EPP\eppDomain($domainname);
            $records = [];
            $records[] = ['type' => 'NS', 'name' => $domainname, 'content' => 'ns1.yourdomainprovider.net', 'ttl' => 3600];
            $records[] = ['type' => 'NS', 'name' => $domainname, 'content' => 'ns2.yourdomainprovider.net', 'ttl' => 3600];
            $records[] = ['type' => 'NS', 'name' => $domainname, 'content' => 'ns3.yourdomainprovider.net', 'ttl' => 3600];
            $request = new \Metaregistrar\EPP\metaregCreateDnsRequest($domain,$records);
            if ($response = $this->conn->request($request)) {
                /* @var $response \Metaregistrar\EPP\metaregInfoDnsResponse */
                return true;
            } else {
                $this->Error[] = 'Error retrieving DNS for '.$domainname;
                return false;
            }

        } catch (Metaregistrar\EPP\eppException $e) {
            $this->Error[] = $e->getMessage();
            return false;
        }
    }


    /**
     * Update the DNS information, add and remove records in the DNS
     * @param string $domainname
     * @param array $adds
     * @param array $dels
     * @return bool
     */
    public function updatednszone($domainname, $adds, $dels) {
        try {
            $domain = new Metaregistrar\EPP\eppDomain($domainname);
            if (count($adds) == 0) {
                $adds = null;
            }
            if (count($dels) == 0) {
                $dels = null;
            }
            $update= new Metaregistrar\EPP\metaregUpdateDnsRequest($domain,$adds,$dels,null);
            $this->Success[] = $update->saveXML();
            if ($response = $this->conn->request($update)) {
                if ($response->getResultCode()==1000) {
                    return true;
                } else {
                    $this->Error[] = $response->getResultMessage();
                    return false;
                }
            } else {
                $this->Error[] = "Error updating DNS";
                return false;
            }
        } catch (\Metaregistrar\EPP\eppException $e) {
            $this->Error[] = $e->getMessage();
            return false;
        }

    }

}