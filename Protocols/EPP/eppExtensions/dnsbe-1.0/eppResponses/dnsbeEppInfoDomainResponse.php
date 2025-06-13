<?php
namespace Metaregistrar\EPP;

use phpseclib3\Math\BigInteger\Engines\PHP;

/**
 * Class dnsbeEppInfoDomainResponse
 * @package Metaregistrar\EPP
 */
class dnsbeEppInfoDomainResponse extends eppInfoDomainResponse {
    function __construct() {
        parent::__construct();
    }


    /**
     * Retrieve a boolean flag if this domain name is in quarantine or not
     * @return bool|null
     */
    public function getQuarantined() {
        $xpath = $this->xPath();
        $result = $xpath->query('/epp:epp/epp:response/epp:extension/dnsbe:ext/dnsbe:infData/dnsbe:domain/dnsbe:quarantined');
        if ($result->length > 0) {
            if ($result->item(0)->nodeValue == 'true') {
                return true;
            } else {
                return false;
            }
        } else {
            return null;
        }
    }


    /**
     * Retrieve a boolean flag if this domain name is on hold or not
     * @return bool|null
     */
    public function getOnHold() {
        $xpath = $this->xPath();
        $result = $xpath->query('/epp:epp/epp:response/epp:extension/dnsbe:ext/dnsbe:infData/dnsbe:domain/dnsbe:onhold');
        if ($result->length > 0) {
            if ($result->item(0)->nodeValue == 'true') {
                return true;
            } else {
                return false;
            }
        } else {
            return null;
        }
    }

    /**
     * @return null|string
     */
    public function getDomainDeletionDate() {
        $xpath = $this->xPath();
        $result = $xpath->query('/epp:epp/epp:response/epp:extension/dnsbe:ext/dnsbe:infData/dnsbe:domain/dnsbe:deletionDate');
        if ($result->length > 0) {
            return $result->item(0)->nodeValue;
        } else {
            return null;
        }
    }

    /**
     * Retrieve a string with the nameserver group
     * @return null|array
     */
    public function getNameserverGroup() {
        $xpath = $this->xPath();
        $result = $xpath->query('/epp:epp/epp:response/epp:extension/dnsbe:ext/dnsbe:infData/dnsbe:domain/dnsbe:nsgroup');
        if ($result->length > 0) {
           $arr = [];
           foreach ($result as $item){
              $arr[]=$item->nodeValue;
           }
           return $arr;
        } else {
            return null;
        }
    }

    /**
     * Retrieve a boolean flag if this domain name is awaiting verification or not.
     *
     * @return bool
     */
    public function getAwaitingVerification()
    {
        $xpath = $this->xPath();
        $result = $xpath->query('/epp:epp/epp:response/epp:extension/dnsbe:ext/dnsbe:infData/dnsbe:domain/dnsbe:nameserversOverridden');

        $nameserversOveridden = $result->item(0)?->nodeValue;

        // If the nameservers are not overridden, the domain is not awaiting verification.
        if (!$nameserversOveridden) {
            return false;
        }

        $reason = $result->item(0)?->attributes?->item(0)?->value;

        return $nameserversOveridden === 'true' && $reason === 'Pending registrant verification';
    }
}

